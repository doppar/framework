<?php

namespace Tests\Unit;

use Phaseolies\Utilities\Paginator;
use Phaseolies\Http\Request;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;

class PaginatorTest extends TestCase
{
    private array $testData = [
        'current_page' => 5,
        'last_page' => 10,
        'previous_page_url' => 'http://example.com?page=4',
        'next_page_url' => 'http://example.com?page=6',
        'path' => 'http://example.com'
    ];

    private Paginator $paginator;

    protected function setUp(): void
    {
        $container = new Container;

        $container->singleton('request', Request::class);

        $this->paginator = new Paginator($this->testData);
    }

    public function testHasPages()
    {
        // Test when there are multiple pages
        $this->assertTrue($this->paginator->hasPages());

        // Test when there's only one page
        $singlePageData = $this->testData;
        $singlePageData['last_page'] = 1;
        $singlePagePaginator = new Paginator($singlePageData);
        $this->assertFalse($singlePagePaginator->hasPages());
    }

    public function testOnFirstPage()
    {
        // Test when not on first page
        $this->assertFalse($this->paginator->onFirstPage());

        // Test when on first page
        $firstPageData = $this->testData;
        $firstPageData['current_page'] = 1;
        $firstPagePaginator = new Paginator($firstPageData);
        $this->assertTrue($firstPagePaginator->onFirstPage());
    }

    public function testHasMorePages()
    {
        // Test when there are more pages
        $this->assertTrue($this->paginator->hasMorePages());

        // Test when on last page
        $lastPageData = $this->testData;
        $lastPageData['current_page'] = 10;
        $lastPagePaginator = new Paginator($lastPageData);
        $this->assertFalse($lastPagePaginator->hasMorePages());
    }

    public function testPreviousPageUrl()
    {
        $this->assertEquals('http://example.com?page=4', $this->paginator->previousPageUrl());

        // Test when on first page (no previous page)
        $firstPageData = $this->testData;
        $firstPageData['current_page'] = 1;
        $firstPageData['previous_page_url'] = null;
        $firstPagePaginator = new Paginator($firstPageData);
        $this->assertNull($firstPagePaginator->previousPageUrl());
    }

    public function testNextPageUrl()
    {
        $this->assertEquals('http://example.com?page=6', $this->paginator->nextPageUrl());

        // Test when on last page (no next page)
        $lastPageData = $this->testData;
        $lastPageData['current_page'] = 10;
        $lastPageData['next_page_url'] = null;
        $lastPagePaginator = new Paginator($lastPageData);
        $this->assertNull($lastPagePaginator->nextPageUrl());
    }

    public function testCurrentPage()
    {
        $this->assertEquals(5, $this->paginator->currentPage());
    }

    public function testLastPage()
    {
        $this->assertEquals(10, $this->paginator->lastPage());
    }

    public function testUrlGeneration()
    {
        $this->assertEquals('http://example.com?page=3', $this->paginator->url(3));
    }

    public function testJumpMethod()
    {
        $expected = [1, '...', 3, 4, 5, 6, 7, '...', 10];

        $this->assertEquals($expected, $this->paginator->jump());

        // Test when current page is near start
        $startPageData = $this->testData;
        $startPageData['current_page'] = 2;
        $startPaginator = new Paginator($startPageData);
        $expectedStart = [1, 2, 3, 4, '...', 10];

        $this->assertEquals($expectedStart, $startPaginator->jump());

        // Test when current page is near end
        $endPageData = $this->testData;
        $endPageData['current_page'] = 9;
        $endPaginator = new Paginator($endPageData);
        $expectedEnd = [1, '...', 7, 8, 9, 10];
        $this->assertEquals($expectedEnd, $endPaginator->jump());

        // Test with small number of pages
        $smallPageData = $this->testData;
        $smallPageData['current_page'] = 2;
        $smallPageData['last_page'] = 3;
        $smallPaginator = new Paginator($smallPageData);
        $expectedSmall = [1, 2, 3];
        $this->assertEquals($expectedSmall, $smallPaginator->jump());
    }

    public function testNumbersMethod()
    {
        $expected = [1, '...', 4, 5, 6, '...', 10];
        $this->assertEquals($expected, $this->paginator->numbers());

        // Test when current page is near start
        $startPageData = $this->testData;
        $startPageData['current_page'] = 2;
        $startPaginator = new Paginator($startPageData);
        $expectedStart = [1, 2, 3, '...', 10];
        $this->assertEquals($expectedStart, $startPaginator->numbers());

        // Test when current page is near end
        $endPageData = $this->testData;
        $endPageData['current_page'] = 9;
        $endPaginator = new Paginator($endPageData);
        $expectedEnd = [1, '...', 8, 9, 10];
        $this->assertEquals($expectedEnd, $endPaginator->numbers());

        // Test with small number of pages
        $smallPageData = $this->testData;
        $smallPageData['current_page'] = 2;
        $smallPageData['last_page'] = 3;
        $smallPaginator = new Paginator($smallPageData);
        $expectedSmall = [1, 2, 3];
        $this->assertEquals($expectedSmall, $smallPaginator->numbers());
    }
}
