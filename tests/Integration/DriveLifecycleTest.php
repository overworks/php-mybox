<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Tests\Integration;

use Minhyung\Mybox\Exception\NotFoundException;
use Minhyung\Mybox\Model\Enum\ResourceType;
use Minhyung\Mybox\Request\CopyOptions;
use PHPUnit\Framework\Attributes\Group;

/**
 * Exercises a full create → upload → read → copy → move → rename → trash →
 * restore → purge cycle against a live account, entirely inside the sandbox.
 */
#[Group('integration')]
final class DriveLifecycleTest extends IntegrationTestCase
{
    public function testStorageReportsAUsableQuota(): void
    {
        $storage = $this->mybox()->drive()->storage();

        self::assertGreaterThan(0, $storage->quotaBytes);
        self::assertGreaterThan(0, $storage->maxFileBytes);
        self::assertGreaterThanOrEqual(0, $storage->usedBytes);
        self::assertContains($storage->trashAutoDeleteDays, [0, 5, 15, 30, 50]);
    }

    public function testTheSandboxIsVisibleAtTheRoot(): void
    {
        foreach ($this->mybox()->drive()->listRootAll() as $item) {
            if ($item->resourceId === $this->sandboxId()) {
                self::assertSame(self::SANDBOX, $item->name);
                self::assertSame(ResourceType::Folder, $item->type);

                return;
            }
        }

        self::fail('The sandbox folder did not appear in the root listing.');
    }

    public function testAFileSurvivesAFullRoundTrip(): void
    {
        $mybox = $this->mybox();
        $contents = 'php-mybox integration ' . bin2hex(random_bytes(8));

        $upload = $mybox->upload()->fromString($contents, 'roundtrip.txt', parentId: $this->sandboxId());
        self::assertLessThan(400, $upload->status);

        $file = $this->findInSandbox('roundtrip.txt');
        self::assertNotNull($file, 'The uploaded file did not appear in the sandbox listing.');
        self::assertSame(strlen($contents), $file->size);

        self::assertSame($contents, $mybox->download()->contents($file->resourceId));
    }

    public function testCopyMoveAndRenameKeepTheDriveConsistent(): void
    {
        $mybox = $this->mybox();

        $subfolder = $mybox->files()->createFolder('sub', $this->sandboxId());
        $mybox->upload()->fromString('original', 'source.txt', parentId: $this->sandboxId());

        $source = $this->findInSandbox('source.txt');
        self::assertNotNull($source);

        $copy = $mybox->files()->copy($source->resourceId, new CopyOptions(
            parentId: $this->sandboxId(),
            name: 'copy.txt',
        ));
        self::assertSame('copy.txt', $copy->name);
        self::assertNotSame($source->resourceId, $copy->resourceId);

        $mybox->files()->move($copy->resourceId, $subfolder->resourceId);
        self::assertSame($subfolder->resourceId, $mybox->drive()->get($copy->resourceId)->parentId);

        self::assertSame('renamed.txt', $mybox->files()->rename($copy->resourceId, 'renamed.txt'));
        self::assertSame('renamed.txt', $mybox->drive()->get($copy->resourceId)->name);
    }

    public function testFavouritingIsIdempotent(): void
    {
        $mybox = $this->mybox();

        self::assertTrue($mybox->drive()->favorite($this->sandboxId())->isFavorite);
        self::assertTrue($mybox->drive()->favorite($this->sandboxId())->isFavorite);
        self::assertFalse($mybox->drive()->unfavorite($this->sandboxId())->isFavorite);
        self::assertFalse($mybox->drive()->unfavorite($this->sandboxId())->isFavorite);
    }

    public function testTrashingRestoringAndPurgingAFile(): void
    {
        $mybox = $this->mybox();

        $mybox->upload()->fromString('disposable', 'trash-me.txt', parentId: $this->sandboxId());
        $file = $this->findInSandbox('trash-me.txt');
        self::assertNotNull($file);

        $mybox->files()->delete($file->resourceId);
        self::assertNull($this->findInSandbox('trash-me.txt'), 'The file is still listed after deletion.');

        // A trashed resource stays readable by id — only its parent changes to
        // a trash container — so the listing, not get(), is what says it went.
        self::assertNotSame(
            $this->sandboxId(),
            $mybox->drive()->get($file->resourceId)->parentId,
            'A trashed file should no longer report the sandbox as its parent.',
        );
        self::assertTrue($this->isInTrash($file->resourceId));

        $mybox->trash()->restore($file->resourceId);
        self::assertNotNull($this->findInSandbox('trash-me.txt'), 'The file did not come back from the trash.');

        $mybox->files()->delete($file->resourceId);
        $mybox->trash()->purge($file->resourceId);

        // Purging is eventually consistent: for well under a second the id is
        // still readable, reporting size 0, before it starts answering 404.
        self::assertTrue(
            $this->eventually(fn (): bool => $this->isGone($file->resourceId)),
            'The purged file never started answering 404.',
        );
    }

    private function isInTrash(string $resourceId): bool
    {
        foreach ($this->mybox()->trash()->listAll() as $item) {
            if ($item->resourceId === $resourceId) {
                return true;
            }
        }

        return false;
    }

    private function isGone(string $resourceId): bool
    {
        try {
            $this->mybox()->drive()->get($resourceId);

            return false;
        } catch (NotFoundException) {
            return true;
        }
    }

    /**
     * Polls a condition, allowing for the drive's eventual consistency.
     *
     * @param \Closure(): bool $condition
     */
    private function eventually(\Closure $condition, int $attempts = 10): bool
    {
        for ($i = 0; $i < $attempts; ++$i) {
            if ($condition()) {
                return true;
            }

            usleep(500_000);
        }

        return false;
    }

    public function testPathsResolveToTheSameIdsTheListingReports(): void
    {
        $mybox = $this->mybox();

        $mybox->upload()->fromString('by path', 'by-path.txt', parentId: $this->sandboxId());
        $file = $this->findInSandbox('by-path.txt');
        self::assertNotNull($file);

        self::assertSame($this->sandboxId(), $mybox->paths()->folderId('/' . self::SANDBOX));
        self::assertSame($file->resourceId, $mybox->paths()->fileId('/' . self::SANDBOX . '/by-path.txt'));
    }

    private function findInSandbox(string $name): ?\Minhyung\Mybox\Model\ResourceItem
    {
        foreach ($this->mybox()->drive()->listFolderAll($this->sandboxId()) as $item) {
            if ($item->name === $name) {
                return $item;
            }
        }

        return null;
    }
}
