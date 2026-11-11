<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Tests\Richdocuments\Controller;

use OCA\Richdocuments\Controller\DocumentAPIController;
use OCA\Richdocuments\Service\OpenLocalPolicyService;
use OCA\Richdocuments\TemplateManager;
use OCP\AppFramework\Http;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\Files\Lock\ILockManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\Share\IManager as IShareManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DocumentAPIControllerTest extends TestCase {
	private const FILE_ID = 42;
	private const USER_ID = 'user1';

	private DocumentAPIController $controller;
	private IRootFolder $rootFolder;
	private OpenLocalPolicyService $openLocalPolicyService;
	private ILockManager $lockManager;

	protected function setUp(): void {
		parent::setUp();

		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->openLocalPolicyService = $this->createMock(OpenLocalPolicyService::class);
		$this->lockManager = $this->createMock(ILockManager::class);

		$this->controller = new DocumentAPIController(
			$this->createMock(IRequest::class),
			$this->rootFolder,
			$this->createMock(IShareManager::class),
			$this->createMock(TemplateManager::class),
			$this->createMock(IL10N::class),
			$this->createMock(LoggerInterface::class),
			$this->lockManager,
			$this->createMock(ISession::class),
			$this->openLocalPolicyService,
			self::USER_ID,
		);
	}

	public function testOpenLocalForbiddenWhenPolicyDenies(): void {
		$file = $this->createMock(File::class);
		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getFirstNodeById')->with(self::FILE_ID)->willReturn($file);
		$this->rootFolder->method('getUserFolder')->with(self::USER_ID)->willReturn($userFolder);

		$this->openLocalPolicyService->expects($this->once())
			->method('isAllowedForFile')
			->with($file, self::USER_ID, null)
			->willReturn(false);

		$this->lockManager->expects($this->never())->method('unlock');

		$response = $this->controller->openLocal(self::FILE_ID);
		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testOpenLocalUnlocksWhenAllowed(): void {
		$file = $this->createMock(File::class);
		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getFirstNodeById')->with(self::FILE_ID)->willReturn($file);
		$this->rootFolder->method('getUserFolder')->with(self::USER_ID)->willReturn($userFolder);

		$this->openLocalPolicyService->method('isAllowedForFile')->willReturn(true);
		$this->lockManager->expects($this->once())->method('unlock');

		$response = $this->controller->openLocal(self::FILE_ID);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}
}
