<?php

declare(strict_types=1);

/**
 * Unit tests for PageController
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ArbeitszeitCheck\Tests\Unit\Controller;

use OCA\ArbeitszeitCheck\Controller\PageController;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Class PageControllerTest
 */
class PageControllerTest extends TestCase
{
	/** @var PageController */
	private $controller;

	/** @var IRequest|\PHPUnit\Framework\MockObject\MockObject */
	private $request;

	protected function setUp(): void
	{
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);

		$this->controller = new PageController(
			'arbeitszeitcheck',
			$this->request
		);
	}

	/**
	 * Test index returns template
	 */
	public function testIndexReturnsTemplate(): void
	{
		$response = $this->controller->index();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('arbeitszeitcheck', $response->getTemplateName());
		$this->assertEquals('index', $response->getRenderAs());
	}

	/**
	 * Test dashboard returns template
	 */
	public function testDashboardReturnsTemplate(): void
	{
		$response = $this->controller->dashboard();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('arbeitszeitcheck', $response->getTemplateName());
		$this->assertEquals('index', $response->getRenderAs());
	}

	/**
	 * Test reports returns template
	 */
	public function testReportsReturnsTemplate(): void
	{
		$response = $this->controller->reports();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('arbeitszeitcheck', $response->getTemplateName());
		$this->assertEquals('index', $response->getRenderAs());
	}

	/**
	 * Test calendar returns template
	 */
	public function testCalendarReturnsTemplate(): void
	{
		$response = $this->controller->calendar();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('arbeitszeitcheck', $response->getTemplateName());
		$this->assertEquals('index', $response->getRenderAs());
	}

	/**
	 * Test timeline returns template
	 */
	public function testTimelineReturnsTemplate(): void
	{
		$response = $this->controller->timeline();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertEquals('arbeitszeitcheck', $response->getTemplateName());
		$this->assertEquals('index', $response->getRenderAs());
	}
}
