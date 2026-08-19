<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Tests\Unit;

use Minhyung\Mybox\Model\ResourceItem;
use Minhyung\Mybox\Model\ResourceList;
use Minhyung\Mybox\Request\ListOptions;
use Minhyung\Mybox\Tests\TestCase;

final class PaginationTest extends TestCase
{
    public function testIterationFollowsCursorsUntilTheServerStops(): void
    {
        $this->willRespond($this->pageOf(['a', 'b'], nextCursor: 'c1'));
        $this->willRespond($this->pageOf(['c'], nextCursor: 'c2'));
        $this->willRespond($this->pageOf(['d']));

        $names = array_map(
            static fn (ResourceItem $item): string => $item->name,
            $this->client()->drive()->listRootAll()->all(),
        );

        self::assertSame(['a', 'b', 'c', 'd'], $names);
        self::assertCount(3, $this->requests());
        self::assertSame('', $this->requests()[0]->getUri()->getQuery());
        self::assertSame('cursor=c1', $this->requests()[1]->getUri()->getQuery());
        self::assertSame('cursor=c2', $this->requests()[2]->getUri()->getQuery());
    }

    public function testTheBaseOptionsAreCarriedOntoEveryPage(): void
    {
        $this->willRespond($this->pageOf(['a'], nextCursor: 'c1'));
        $this->willRespond($this->pageOf(['b']));

        $this->client()->drive()->listFolderAll('folder-1', new ListOptions(count: 500))->all();

        self::assertSame('count=500', $this->requests()[0]->getUri()->getQuery());
        self::assertSame('count=500&cursor=c1', urldecode($this->requests()[1]->getUri()->getQuery()));
    }

    public function testTakeStopsRequestingOnceItHasEnoughItems(): void
    {
        $this->willRespond($this->pageOf(['a', 'b'], nextCursor: 'c1'));
        $this->willRespond($this->pageOf(['c'], nextCursor: 'c2'));

        $items = $this->client()->drive()->listRootAll()->take(2);

        self::assertCount(2, $items);
        self::assertCount(1, $this->requests(), 'The second page should never have been requested.');
    }

    public function testTakeWithANonPositiveLimitMakesNoRequestAtAll(): void
    {
        self::assertSame([], $this->client()->drive()->listRootAll()->take(0));
        self::assertSame([], $this->requests());
    }

    public function testPagesYieldsTheMetadataAlongsideTheItems(): void
    {
        $this->willRespond($this->pageOf(['a'], nextCursor: 'c1', fileCount: 7));
        $this->willRespond($this->pageOf(['b'], fileCount: 7));

        $cursors = [];

        foreach ($this->client()->drive()->listRootAll()->pages() as $page) {
            self::assertInstanceOf(ResourceList::class, $page);
            $cursors[] = $page->nextCursor();
            self::assertSame(7, $page->fileCount);
        }

        self::assertSame(['c1', null], $cursors);
    }

    public function testARepeatedCursorTerminatesIterationInsteadOfLoopingForever(): void
    {
        $this->willRespond($this->pageOf(['a'], nextCursor: 'same'));
        $this->willRespond($this->pageOf(['b'], nextCursor: 'same'));
        $this->willRespond($this->pageOf(['c'], nextCursor: 'same'));

        $items = $this->client()->drive()->listRootAll()->all();

        self::assertCount(2, $items);
        self::assertCount(2, $this->requests());
    }

    public function testAnEmptyCursorIsTreatedAsTheEnd(): void
    {
        $this->willRespond($this->pageOf(['a'], nextCursor: ''));

        self::assertCount(1, $this->client()->drive()->listRootAll()->all());
        self::assertCount(1, $this->requests());
    }

    /**
     * @param  list<string>         $names
     * @return array<string, mixed>
     */
    private function pageOf(array $names, ?string $nextCursor = null, int $fileCount = 0): array
    {
        $resources = [];

        foreach ($names as $i => $name) {
            $resources[] = [
                'accessedAt' => '2026-08-11T09:00:00+09:00',
                'createdAt' => '2026-08-11T09:00:00+09:00',
                'modifiedAt' => '2026-08-11T09:00:00+09:00',
                'isFavorite' => false,
                'isHidden' => false,
                'lastModifiedBy' => 'mybox_user',
                'name' => $name,
                'parentId' => 'root',
                'resourceId' => 'res-' . $i . '-' . $name,
                'size' => 1,
                'type' => 'file',
            ];
        }

        return [
            'fileCount' => $fileCount,
            'subFolderCount' => 0,
            'resources' => $resources,
            'responseMetaData' => $nextCursor === null ? [] : ['nextCursor' => $nextCursor],
        ];
    }
}
