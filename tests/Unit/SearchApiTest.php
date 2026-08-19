<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Tests\Unit;

use Minhyung\Mybox\Exception\InvalidArgumentException;
use Minhyung\Mybox\Model\Enum\Category;
use Minhyung\Mybox\Model\Enum\DateField;
use Minhyung\Mybox\Request\SearchFilesOptions;
use Minhyung\Mybox\Request\SearchFoldersOptions;
use Minhyung\Mybox\Tests\TestCase;

final class SearchApiTest extends TestCase
{
    public function testFileSearchParsesThePathProjection(): void
    {
        $this->willRespondWithFixture('search_files');

        $page = $this->client()->search()->files(new SearchFilesOptions(q: '사진'));

        $this->assertRequest('GET', '/v1/search/resources/files?q=사진');
        self::assertSame('Mjk5MTIzNDU2Nzg5MDE', $page->nextCursor());
        self::assertCount(1, $page->resources);

        $file = $page->resources[0];
        self::assertSame('사진.jpg', $file->name);
        self::assertSame('/문서/사진.jpg', $file->path);
        self::assertSame('/문서/', $file->parentPath);
        self::assertSame(Category::Image, $file->category);
        self::assertSame(1048576, $file->size);
    }

    public function testFileSearchSerialisesEveryFilter(): void
    {
        $this->willRespondWithFixture('search_files');

        $this->client()->search()->files(new SearchFilesOptions(
            q: '1월 회의록 pdf',
            category: Category::Document,
            startDate: new \DateTimeImmutable('2026-01-01T00:00:00+09:00'),
            endDate: new \DateTimeImmutable('2026-02-01T00:00:00+09:00'),
            dateField: DateField::Modified,
            parentPath: '/문서/',
            count: 200,
        ));

        $this->assertRequest(
            'GET',
            '/v1/search/resources/files'
            . '?q=1월 회의록 pdf'
            . '&category=document'
            . '&startDate=2026-01-01T00:00:00+09:00'
            . '&endDate=2026-02-01T00:00:00+09:00'
            . '&dateField=modified'
            . '&parentPath=/문서/'
            . '&count=200',
        );
    }

    public function testFileSearchRequiresAtLeastOneCriterion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one of: q, category, startDate, or endDate');

        new SearchFilesOptions();
    }

    public function testFileSearchRejectsAPageSizeOutsideTheSearchRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('count must be between 20 and 200');

        new SearchFilesOptions(q: 'x', count: 10);
    }

    public function testFileSearchRejectsAnInvertedDateRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('startDate must not be later than endDate');

        new SearchFilesOptions(
            startDate: new \DateTimeImmutable('2026-02-01T00:00:00+09:00'),
            endDate: new \DateTimeImmutable('2026-01-01T00:00:00+09:00'),
        );
    }

    public function testFolderSearchAcceptsAnExactPathAsItsSoleCriterion(): void
    {
        $this->willRespondWithFixture('search_folders');

        $page = $this->client()->search()->folders(new SearchFoldersOptions(path: '/문서/사진/'));

        $this->assertRequest('GET', '/v1/search/resources/folders?path=/문서/사진/');
        self::assertSame('사진', $page->resources[0]->name);
        self::assertSame('/문서/사진/', $page->resources[0]->path);
    }

    public function testFolderSearchRequiresAtLeastOneCriterion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one of: q, path, startDate, or endDate');

        new SearchFoldersOptions();
    }
}
