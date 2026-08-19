<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Tests\Unit;

use Minhyung\Mybox\Model\Enum\SortOrder;
use Minhyung\Mybox\Model\Enum\TrashSortField;
use Minhyung\Mybox\Request\TrashListOptions;
use Minhyung\Mybox\Tests\TestCase;

final class TrashApiTest extends TestCase
{
    public function testListParsesDeletedAt(): void
    {
        $this->willRespondWithFixture('trash_list');

        $page = $this->client()->trash()->list();

        $this->assertRequest('GET', '/v1/drive/trash');
        self::assertSame(12, $page->fileCount);
        self::assertSame('MjA', $page->nextCursor());

        $item = $page->resources[0];
        self::assertSame('회의록.pdf', $item->name);
        self::assertSame('2026-08-11T10:00:00+09:00', $item->deletedAt->format(\DateTimeInterface::ATOM));
        self::assertFalse($item->isFolder());
    }

    public function testListSerialisesTheTrashSpecificSortKeys(): void
    {
        $this->willRespondWithFixture('trash_list');

        $this->client()->trash()->list(new TrashListOptions(TrashSortField::Location, SortOrder::Asc, count: 50));

        $this->assertRequest('GET', '/v1/drive/trash?sort=location,asc&count=50');
    }

    public function testRestoreSendsTheOverwriteFlag(): void
    {
        $this->willRespondEmpty(200);

        $this->client()->trash()->restore('res-1', isOverwrite: true);

        $this->assertRequest('POST', '/v1/drive/trash/res-1/restore');
        self::assertSame(['isOverwrite' => true], $this->lastRequestBody());
    }

    public function testPurgeDeletesASingleTrashedResource(): void
    {
        $this->willRespondEmpty();

        $this->client()->trash()->purge('res-1');

        $this->assertRequest('DELETE', '/v1/drive/trash/res-1');
    }

    public function testEmptyDeletesTheWholeTrash(): void
    {
        $this->willRespondEmpty();

        $this->client()->trash()->empty();

        $this->assertRequest('DELETE', '/v1/drive/trash');
    }
}
