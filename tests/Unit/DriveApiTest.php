<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Tests\Unit;

use Minhyung\Mybox\Exception\InvalidArgumentException;
use Minhyung\Mybox\Model\Enum\Category;
use Minhyung\Mybox\Model\Enum\ResourceType;
use Minhyung\Mybox\Model\Enum\SortField;
use Minhyung\Mybox\Model\Enum\SortOrder;
use Minhyung\Mybox\Request\ListOptions;
use Minhyung\Mybox\Tests\TestCase;

final class DriveApiTest extends TestCase
{
    public function testStorageParsesQuotaAndPerCategoryCounts(): void
    {
        $this->willRespondWithFixture('storage');

        $storage = $this->client()->drive()->storage();

        $this->assertRequest('GET', '/v1/drive/storage');
        self::assertSame(32212254720, $storage->quotaBytes);
        self::assertSame(5368709120, $storage->usedBytes);
        self::assertSame(53687091200, $storage->maxFileBytes);
        self::assertSame(5, $storage->trashAutoDeleteDays);
        self::assertSame(120, $storage->fileCounts->total);
        self::assertSame(40, $storage->fileCounts->of(Category::Image));
        self::assertSame(26843545600, $storage->freeBytes());
    }

    public function testSetTrashAutoDeleteDaysSendsPatch(): void
    {
        $this->willRespondWithFixture('trash_routine');

        $days = $this->client()->drive()->setTrashAutoDeleteDays(5);

        $this->assertRequest('PATCH', '/v1/drive/storage');
        self::assertSame(['trashAutoDeleteDays' => 5], $this->lastRequestBody());
        self::assertSame(5, $days);
    }

    public function testSetTrashAutoDeleteDaysRejectsUnsupportedInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be one of 0, 5, 15, 30, 50');

        $this->client()->drive()->setTrashAutoDeleteDays(7);
    }

    public function testListRootParsesResourcesAndCursor(): void
    {
        $this->willRespondWithFixture('resource_list');

        $page = $this->client()->drive()->listRoot();

        $this->assertRequest('GET', '/v1/drive/resources');
        self::assertSame(12, $page->fileCount);
        self::assertSame(3, $page->subFolderCount);
        self::assertSame('MjA', $page->nextCursor());
        self::assertCount(1, $page->resources);

        $item = $page->resources[0];
        self::assertSame('회의록.pdf', $item->name);
        self::assertSame('hV3sQ9pLzR2mT7kXwB5nDcF8gJ4yA6uE0o', $item->resourceId);
        self::assertSame(ResourceType::File, $item->type);
        self::assertSame(Category::Image, $item->category);
        self::assertSame(1048576, $item->size);
        self::assertFalse($item->isFavorite);
        self::assertSame('2026-08-11T09:00:00+09:00', $item->createdAt->format(\DateTimeInterface::ATOM));
        self::assertNull($item->fileCount);
    }

    public function testListRootSerialisesSortAndPaging(): void
    {
        $this->willRespondWithFixture('resource_list');

        $this->client()->drive()->listRoot(
            new ListOptions(SortField::ModifiedAt, SortOrder::Desc, count: 250, cursor: 'MjA'),
        );

        $this->assertRequest('GET', '/v1/drive/resources?sort=modifiedAt,desc&count=250&cursor=MjA');
    }

    public function testListOptionsRejectsOversizedPage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('count must be between 1 and 1000');

        new ListOptions(count: 1001);
    }

    public function testListFolderEncodesTheFolderIdIntoThePath(): void
    {
        $this->willRespondWithFixture('resource_list');

        $this->client()->drive()->listFolder('a/b+c');

        self::assertSame('/v1/drive/folders/a%2Fb%2Bc/resources', $this->lastRequest()->getUri()->getPath());
    }

    public function testGetReturnsFolderCountsOnTheDetailEndpoint(): void
    {
        $this->willRespondWithFixture('resource_detail');

        $item = $this->client()->drive()->get('hV3sQ9pLzR2mT7kXwB5nDcF8gJ4yA6uE0o');

        $this->assertRequest('GET', '/v1/drive/resources/hV3sQ9pLzR2mT7kXwB5nDcF8gJ4yA6uE0o');
        self::assertSame(12, $item->fileCount);
        self::assertSame(3, $item->subFolderCount);
        self::assertTrue($item->isFile());
    }

    public function testFavoriteAndUnfavoriteHitTheirOwnEndpoints(): void
    {
        $this->willRespondWithFixture('favorite');
        $this->willRespondWithFixture('unfavorite');

        $client = $this->client();

        $on = $client->drive()->favorite('res-1');
        $this->assertRequest('POST', '/v1/drive/resources/res-1/favorite');
        self::assertTrue($on->isFavorite);

        $off = $client->drive()->unfavorite('res-1');
        $this->assertRequest('POST', '/v1/drive/resources/res-1/unfavorite');
        self::assertFalse($off->isFavorite);
    }
}
