<?php

declare(strict_types=1);

namespace AzureOss\FlysystemAzureBlobStorage\Tests;

use AzureOss\FlysystemAzureBlobStorage\AzureBlobStorageAdapter;
use AzureOss\Storage\Blob\BlobContainerClient;
use AzureOss\Storage\Blob\BlobServiceClient;
use League\Flysystem\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\Visibility;
use PHPUnit\Framework\Attributes\Test;

class AzureBlobStorageTest extends FilesystemAdapterTestCase
{
    public const CONTAINER_NAME = 'flysystem';

    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        $connectionString = getenv('FLYSYSTEM_AZURE_CONNECTION_STRING');

        if (empty($connectionString)) {
            self::markTestSkipped('FLYSYSTEM_AZURE_CONNECTION_STRING is not provided.');
        }

        return new AzureBlobStorageAdapter(
            self::createContainerClient(),
            'flysystem',
        );
    }

    private static function createContainerClient(): BlobContainerClient
    {
        $connectionString = getenv('FLYSYSTEM_AZURE_CONNECTION_STRING');

        if (empty($connectionString)) {
            self::markTestSkipped('FLYSYSTEM_AZURE_CONNECTION_STRING is not provided.');
        }

        return BlobServiceClient::fromConnectionString($connectionString)->getContainerClient('flysystem');
    }

    public static function setUpBeforeClass(): void
    {
        self::createContainerClient()->deleteIfExists();
        self::createContainerClient()->create();
    }

    public function overwriting_a_file(): void
    {
        $this->runScenario(
            function () {
                $this->givenWeHaveAnExistingFile('path.txt');
                $adapter = $this->adapter();

                $adapter->write('path.txt', 'new contents', new Config());

                $contents = $adapter->read('path.txt');
                $this->assertEquals('new contents', $contents);
            },
        );
    }

    public function setting_visibility(): void
    {
        self::markTestSkipped('Azure does not support visibility');
    }

    public function fetching_unknown_mime_type_of_a_file(): void
    {
        $this->markTestSkipped('This adapter always returns a mime-type');
    }

    public function listing_contents_recursive(): void
    {
        $this->markTestSkipped('This adapter does not support creating directories');
    }

    public function copying_a_file(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'source.txt',
                'contents to be copied',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]),
            );

            $adapter->copy('source.txt', 'destination.txt', new Config());

            $this->assertTrue($adapter->fileExists('source.txt'));
            $this->assertTrue($adapter->fileExists('destination.txt'));
            $this->assertEquals('contents to be copied', $adapter->read('destination.txt'));
        });
    }

    public function moving_a_file(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'source.txt',
                'contents to be copied',
                new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]),
            );
            $adapter->move('source.txt', 'destination.txt', new Config());
            $this->assertFalse(
                $adapter->fileExists('source.txt'),
                'After moving a file should no longer exist in the original location.',
            );
            $this->assertTrue(
                $adapter->fileExists('destination.txt'),
                'After moving, a file should be present at the new location.',
            );
            $this->assertEquals('contents to be copied', $adapter->read('destination.txt'));
        });
    }

    public function copying_a_file_again(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();
            $adapter->write(
                'source.txt',
                'contents to be copied',
                new Config(),
            );

            $adapter->copy('source.txt', 'destination.txt', new Config());

            $this->assertTrue($adapter->fileExists('source.txt'));
            $this->assertTrue($adapter->fileExists('destination.txt'));
            $this->assertEquals('contents to be copied', $adapter->read('destination.txt'));
        });
    }

    public function checking_if_a_directory_exists_after_creating_it(): void
    {
        $this->markTestSkipped('This adapter does not support creating directories');
    }

    public function setting_visibility_on_a_file_that_does_not_exist(): void
    {
        $this->markTestSkipped('This adapter does not support visibility');
    }

    public function creating_a_directory(): void
    {
        $this->markTestSkipped('This adapter does not support creating directories');
    }

    public function file_exists_on_directory_is_false(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();

            $this->assertFalse($adapter->directoryExists('test'));

            $adapter->write("test/file.txt", "", new Config());

            $this->assertTrue($adapter->directoryExists('test'));
            $this->assertFalse($adapter->fileExists('test'));
        });
    }

    #[Test]
    public function setting_visibility_can_be_ignored_not_supported(): void
    {
        $this->givenWeHaveAnExistingFile('some-file.md');
        $this->expectNotToPerformAssertions();

        $adapter = new AzureBlobStorageAdapter(
            self::createContainerClient(),
            visibilityHandling: AzureBlobStorageAdapter::ON_VISIBILITY_IGNORE,
        );

        $adapter->setVisibility('some-file.md', 'public');
    }

    #[Test]
    public function setting_visibility_causes_errors(): void
    {
        $this->givenWeHaveAnExistingFile('some-file.md');
        $adapter = $this->adapter();

        $this->expectException(UnableToSetVisibility::class);

        $adapter->setVisibility('some-file.md', 'public');
    }

    #[Test]
    public function listing_contents_deep(): void
    {
        $this->runScenario(function () {
            $adapter = $this->adapter();

            $adapter->write('dir1/file1.txt', 'content1', new Config());
            $adapter->write('dir1/dir2/file2.txt', 'content2', new Config());
            $adapter->write('dir1/dir2/dir3/file3.txt', 'content3', new Config());
            /** @phpstan-ignore-next-line */
            $contents = iterator_to_array($adapter->listContents('', true));

            $this->assertCount(6, $contents); // 3 files + 3 directories

            $paths = array_map(fn($item) => $item->path(), $contents);
            $this->assertContains('dir1', $paths);
            $this->assertContains('dir1/file1.txt', $paths);
            $this->assertContains('dir1/dir2', $paths);
            $this->assertContains('dir1/dir2/file2.txt', $paths);
            $this->assertContains('dir1/dir2/dir3', $paths);
            $this->assertContains('dir1/dir2/dir3/file3.txt', $paths);
        });
    }
}
