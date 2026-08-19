<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Tests\Unit;

use Minhyung\Mybox\Exception\InvalidArgumentException;
use Minhyung\Mybox\Request\CopyOptions;
use Minhyung\Mybox\Request\UploadRequest;
use Minhyung\Mybox\Tests\TestCase;

final class FileApiTest extends TestCase
{
    public function testCreateFolderPostsTheName(): void
    {
        $this->willRespondWithFixture('create_folder');

        $ref = $this->client()->files()->createFolder('업무자료');

        $this->assertRequest('POST', '/v1/drive/folders');
        self::assertSame(['folderName' => '업무자료'], $this->lastRequestBody());
        self::assertSame('Kd7ZmR2vT9xQ4nB6wL1yHc3pJ8sF5gA0uE', $ref->resourceId);
        self::assertSame('업무자료', $ref->name);
    }

    public function testCreateFolderOmitsParentIdWhenTargetingTheRoot(): void
    {
        $this->willRespondWithFixture('create_folder');

        $this->client()->files()->createFolder('업무자료', 'parent-1');

        self::assertSame(['folderName' => '업무자료', 'parentId' => 'parent-1'], $this->lastRequestBody());
    }

    public function testCreateFolderRejectsABlankName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client()->files()->createFolder('   ');
    }

    public function testCreateUploadUrlSendsTheDeclaredSize(): void
    {
        $this->willRespondWithFixture('upload_ticket');

        $ticket = $this->client()->files()->createUploadUrl(
            new UploadRequest(fileName: 'report.pdf', fileSize: 2048, parentId: 'folder-1', isOverwrite: true),
        );

        $this->assertRequest('POST', '/v1/drive/files');
        self::assertSame([
            'fileName' => 'report.pdf',
            'fileSize' => 2048,
            'parentId' => 'folder-1',
            'isOverwrite' => true,
        ], $this->lastRequestBody());
        self::assertStringContainsString('stoken=abc', $ticket->uploadUrl);
        self::assertSame(0, $ticket->offset);
        self::assertFalse($ticket->isResumed());
    }

    public function testUploadRequestRequiresModifiedTimeWhenResuming(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('modifiedTime is required when resume is true');

        new UploadRequest(fileName: 'a.bin', fileSize: 1, resume: true);
    }

    public function testUploadRequestRejectsANegativeSize(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UploadRequest(fileName: 'a.bin', fileSize: -1);
    }

    public function testUploadRequestSerialisesResumeFields(): void
    {
        $body = (new UploadRequest(
            fileName: 'a.bin',
            fileSize: 10,
            resume: true,
            modifiedTime: new \DateTimeImmutable('2026-08-11T09:00:00+09:00'),
        ))->toBody();

        self::assertSame([
            'fileName' => 'a.bin',
            'fileSize' => 10,
            'resume' => true,
            'modifiedTime' => '2026-08-11T09:00:00+09:00',
        ], $body);
    }

    public function testModifiedTimeIsAlwaysSentAsKstWhateverTheCallersTimezone(): void
    {
        // MYBOX matches an interrupted upload by the literal modifiedTime
        // string and only recognises the KST spelling, so an identical instant
        // written in another zone would silently restart the upload.
        $instant = new \DateTimeImmutable('2026-01-02T03:04:05+09:00');

        foreach (['UTC', 'America/New_York', 'Asia/Seoul', 'Australia/Sydney'] as $timezone) {
            $body = (new UploadRequest(
                fileName: 'a.bin',
                fileSize: 10,
                resume: true,
                modifiedTime: $instant->setTimezone(new \DateTimeZone($timezone)),
            ))->toBody();

            self::assertSame(
                '2026-01-02T03:04:05+09:00',
                $body['modifiedTime'],
                sprintf('A caller in %s should still send KST.', $timezone),
            );
        }
    }

    public function testAMutableDateTimeIsAcceptedForModifiedTime(): void
    {
        $body = (new UploadRequest(
            fileName: 'a.bin',
            fileSize: 10,
            resume: true,
            modifiedTime: new \DateTime('2026-01-01T18:04:05+00:00'),
        ))->toBody();

        self::assertSame('2026-01-02T03:04:05+09:00', $body['modifiedTime']);
    }

    public function testCreateDownloadUrlReturnsTheTicket(): void
    {
        $this->willRespondWithFixture('download_ticket');

        $ticket = $this->client()->files()->createDownloadUrl('file-1');

        $this->assertRequest('GET', '/v1/drive/files/file-1/download');
        self::assertSame(600, $ticket->expiresIn);
        self::assertStringContainsString('atoken=t', $ticket->downloadUrl);
    }

    public function testCopySendsOnlyTheOptionsThatWereSet(): void
    {
        $this->willRespondWithFixture('copy');

        $this->client()->files()->copy('res-1', new CopyOptions(parentId: 'folder-2', name: '사본.pdf'));

        $this->assertRequest('POST', '/v1/drive/resources/res-1/copy');
        self::assertSame(['parentId' => 'folder-2', 'name' => '사본.pdf'], $this->lastRequestBody());
    }

    public function testCopyWithoutOptionsSendsAnEmptyBody(): void
    {
        $this->willRespondWithFixture('copy');

        $this->client()->files()->copy('res-1');

        self::assertSame([], $this->lastRequestBody());
    }

    public function testDeleteAcceptsA204WithNoBody(): void
    {
        $this->willRespondEmpty();

        $this->client()->files()->delete('res-1');

        $this->assertRequest('DELETE', '/v1/drive/resources/res-1');
    }

    public function testMoveSendsTheDestinationAndOverwriteFlag(): void
    {
        $this->willRespondEmpty(200);

        $this->client()->files()->move('res-1', 'folder-2', isOverwrite: true);

        $this->assertRequest('POST', '/v1/drive/resources/res-1/move');
        self::assertSame(['parentId' => 'folder-2', 'isOverwrite' => true], $this->lastRequestBody());
    }

    public function testMoveRejectsAnEmptyDestination(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->client()->files()->move('res-1', '');
    }

    public function testRenameReturnsTheNameTheServerConfirms(): void
    {
        $this->willRespondWithFixture('rename');

        $name = $this->client()->files()->rename('res-1', '회의록.pdf');

        $this->assertRequest('POST', '/v1/drive/resources/res-1/rename');
        self::assertSame(['name' => '회의록.pdf'], $this->lastRequestBody());
        self::assertSame('회의록.pdf', $name);
    }
}
