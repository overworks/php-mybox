<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Tests\Unit;

use Minhyung\Mybox\Exception\NotFoundException;
use Minhyung\Mybox\Tests\TestCase;

final class PathResolverTest extends TestCase
{
    public function testAFolderPathResolvesInASingleSearchCall(): void
    {
        $this->willRespond($this->folderHits(['/문서/사진/' => 'folder-photos']));

        $id = $this->client()->paths()->folderId('/문서/사진');

        self::assertSame('folder-photos', $id);
        self::assertCount(1, $this->requests());
        self::assertSame('/v1/search/resources/folders', $this->lastRequest()->getUri()->getPath());
        self::assertSame('path=/문서/사진/', urldecode($this->lastRequest()->getUri()->getQuery()));
    }

    public function testAResolvedFolderIsNotLookedUpTwice(): void
    {
        $this->willRespond($this->folderHits(['/문서/' => 'folder-docs']));

        $paths = $this->client()->paths();

        self::assertSame('folder-docs', $paths->folderId('문서'));
        self::assertSame('folder-docs', $paths->folderId('/문서/'));
        self::assertCount(1, $this->requests());
    }

    public function testClearingTheCacheForcesAFreshLookup(): void
    {
        $this->willRespond($this->folderHits(['/문서/' => 'folder-docs']));
        $this->willRespond($this->folderHits(['/문서/' => 'folder-docs-renamed']));

        $paths = $this->client()->paths();

        self::assertSame('folder-docs', $paths->folderId('/문서'));
        $paths->clearCache();
        self::assertSame('folder-docs-renamed', $paths->folderId('/문서'));
        self::assertCount(2, $this->requests());
    }

    public function testWhenSearchMissesTheResolverWalksDownFromTheRoot(): void
    {
        $this->willRespond($this->folderHits([]));
        $this->willRespond($this->listing([['문서', 'folder', 'folder-docs']]));
        $this->willRespond($this->listing([['2026', 'folder', 'folder-2026']]));

        self::assertSame('folder-2026', $this->client()->paths()->folderId('/문서/2026'));

        self::assertCount(3, $this->requests());
        self::assertSame('/v1/drive/resources', $this->requests()[1]->getUri()->getPath());
        self::assertSame('/v1/drive/folders/folder-docs/resources', $this->requests()[2]->getUri()->getPath());
    }

    public function testAFolderThatExistsNowhereRaisesNotFound(): void
    {
        $this->willRespond($this->folderHits([]));
        $this->willRespond($this->listing([]));

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('No folder found at path "/없음"');

        $this->client()->paths()->folderId('/없음');
    }

    public function testTheRootItselfHasNoResolvableId(): void
    {
        $this->expectException(\Minhyung\Mybox\Exception\InvalidArgumentException::class);

        $this->client()->paths()->folderId('/');
    }

    public function testAFileIsFoundAmongItsParentsChildren(): void
    {
        $this->willRespond($this->folderHits(['/문서/' => 'folder-docs']));
        $this->willRespond($this->listing([
            ['사진', 'folder', 'folder-photos'],
            ['회의록.pdf', 'file', 'file-minutes'],
        ]));

        self::assertSame('file-minutes', $this->client()->paths()->fileId('/문서/회의록.pdf'));
    }

    public function testAFileAtTheRootNeedsNoFolderLookup(): void
    {
        $this->willRespond($this->listing([['메모.txt', 'file', 'file-memo']]));

        self::assertSame('file-memo', $this->client()->paths()->fileId('메모.txt'));
        self::assertCount(1, $this->requests());
        self::assertSame('/v1/drive/resources', $this->lastRequest()->getUri()->getPath());
    }

    public function testAFolderIsNotMistakenForAFileOfTheSameName(): void
    {
        $this->willRespond($this->listing([['보고서', 'folder', 'folder-reports']]));

        $this->expectException(NotFoundException::class);

        $this->client()->paths()->fileId('/보고서');
    }

    public function testResourceIdFallsBackFromFileToFolder(): void
    {
        $this->willRespond($this->listing([['보고서', 'folder', 'folder-reports']]));
        $this->willRespond($this->folderHits(['/보고서/' => 'folder-reports']));

        self::assertSame('folder-reports', $this->client()->paths()->resourceId('/보고서'));
    }

    /**
     * @param  array<string, string> $pathToId
     * @return array<string, mixed>
     */
    private function folderHits(array $pathToId): array
    {
        $resources = [];

        foreach ($pathToId as $path => $id) {
            $resources[] = [
                'createdAt' => '2026-06-26T15:04:05+09:00',
                'modifiedAt' => '2026-06-26T15:04:05+09:00',
                'name' => basename(rtrim($path, '/')),
                'parentId' => 'parent',
                'parentPath' => '/',
                'path' => $path,
                'resourceId' => $id,
            ];
        }

        return ['resources' => $resources, 'responseMetaData' => []];
    }

    /**
     * @param  list<array{string, string, string}> $rows Name, type, resource id.
     * @return array<string, mixed>
     */
    private function listing(array $rows): array
    {
        $resources = [];

        foreach ($rows as [$name, $type, $id]) {
            $resources[] = [
                'accessedAt' => '2026-08-11T09:00:00+09:00',
                'createdAt' => '2026-08-11T09:00:00+09:00',
                'modifiedAt' => '2026-08-11T09:00:00+09:00',
                'isFavorite' => false,
                'isHidden' => false,
                'lastModifiedBy' => 'mybox_user',
                'name' => $name,
                'parentId' => 'root',
                'resourceId' => $id,
                'size' => 0,
                'type' => $type,
            ];
        }

        return [
            'fileCount' => count($rows),
            'subFolderCount' => 0,
            'resources' => $resources,
            'responseMetaData' => [],
        ];
    }
}
