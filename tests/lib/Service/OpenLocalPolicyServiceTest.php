<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Tests\Richdocuments\Service;

use OCA\Richdocuments\AppInfo\Application;
use OCA\Richdocuments\Db\Wopi;
use OCA\Richdocuments\PermissionManager;
use OCA\Richdocuments\Service\OpenLocalPolicyService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IUser;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OpenLocalPolicyServiceTest extends TestCase {
	private IConfig&MockObject $config;
	private PermissionManager&MockObject $permissionManager;
	private OpenLocalPolicyService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->permissionManager = $this->createMock(PermissionManager::class);
		$this->service = new OpenLocalPolicyService($this->config, $this->permissionManager);
	}

	public function testGloballyDisabledDenies(): void {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with(Application::APPNAME, 'open_local_editor', 'yes')
			->willReturn('no');

		$file = $this->createMock(File::class);
		$this->permissionManager->expects($this->never())->method('shouldWatermark');

		$this->assertFalse($this->service->isAllowedForFile($file, 'user1'));
	}

	public function testFolderIsDenied(): void {
		$this->config->method('getAppValue')->willReturn('yes');
		$folder = $this->createMock(Folder::class);
		$this->permissionManager->expects($this->never())->method('shouldWatermark');

		$this->assertFalse($this->service->isAllowedForFile($folder, 'user1'));
	}

	public function testSecureViewDenies(): void {
		$this->config->method('getAppValue')->willReturn('yes');

		$owner = $this->createMock(IUser::class);
		$owner->method('getUID')->willReturn('owner1');

		$file = $this->createMock(File::class);
		$file->method('getOwner')->willReturn($owner);

		$this->permissionManager->expects($this->once())
			->method('shouldWatermark')
			->with($file, 'user1', null, 'owner1')
			->willReturn(true);

		$this->assertFalse($this->service->isAllowedForFile($file, 'user1'));
	}

	public function testAllowedWhenNotSecureView(): void {
		$this->config->method('getAppValue')->willReturn('yes');

		$owner = $this->createMock(IUser::class);
		$owner->method('getUID')->willReturn('owner1');

		$file = $this->createMock(File::class);
		$file->method('getOwner')->willReturn($owner);

		$share = $this->createMock(IShare::class);

		$this->permissionManager->expects($this->once())
			->method('shouldWatermark')
			->with($file, 'user1', $share, 'owner1')
			->willReturn(false);

		$this->assertTrue($this->service->isAllowedForFile($file, 'user1', $share));
	}

	public function testHideDownloadOnWopiDenies(): void {
		$this->config->method('getAppValue')->willReturn('yes');

		// Wopi getters are Entity magic methods - use a real entity instance.
		$wopi = Wopi::fromParams([
			'fileid' => 1,
			'hideDownload' => true,
			'editorUid' => 'editor1',
		]);

		$file = $this->createMock(File::class);
		$this->permissionManager->expects($this->never())->method('shouldWatermark');

		$this->assertFalse($this->service->isAllowedForWopi($wopi, $file));
	}

	public function testWopiAllowedWhenNotRestricted(): void {
		$this->config->method('getAppValue')->willReturn('yes');

		$owner = $this->createMock(IUser::class);
		$owner->method('getUID')->willReturn('owner1');

		$file = $this->createMock(File::class);
		$file->method('getOwner')->willReturn($owner);

		$wopi = Wopi::fromParams([
			'fileid' => 1,
			'hideDownload' => false,
			'editorUid' => 'editor1',
		]);

		$this->permissionManager->expects($this->once())
			->method('shouldWatermark')
			->with($file, 'editor1', null, 'owner1')
			->willReturn(false);

		$this->assertTrue($this->service->isAllowedForWopi($wopi, $file));
	}

	public function testIsAllowedForUserPathResolvesAndDelegates(): void {
		$this->config->method('getAppValue')->willReturn('yes');

		$owner = $this->createMock(IUser::class);
		$owner->method('getUID')->willReturn('owner1');

		$file = $this->createMock(File::class);
		$file->method('getOwner')->willReturn($owner);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->once())
			->method('get')
			->with('Documents/plan.odt')
			->willReturn($file);

		$root = $this->createMock(IRootFolder::class);
		$root->expects($this->once())
			->method('getUserFolder')
			->with('user1')
			->willReturn($userFolder);

		$this->permissionManager->expects($this->once())
			->method('shouldWatermark')
			->with($file, 'user1', null, 'owner1')
			->willReturn(false);

		$this->assertTrue($this->service->isAllowedForUserPath($root, 'user1', '/Documents/plan.odt'));
	}

	public function testIsAllowedForUserPathDeniesSecureView(): void {
		$this->config->method('getAppValue')->willReturn('yes');

		$owner = $this->createMock(IUser::class);
		$owner->method('getUID')->willReturn('owner1');

		$file = $this->createMock(File::class);
		$file->method('getOwner')->willReturn($owner);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('get')->with('secret.odt')->willReturn($file);

		$root = $this->createMock(IRootFolder::class);
		$root->method('getUserFolder')->with('user1')->willReturn($userFolder);

		$this->permissionManager->expects($this->once())
			->method('shouldWatermark')
			->with($file, 'user1', null, 'owner1')
			->willReturn(true);

		$this->assertFalse($this->service->isAllowedForUserPath($root, 'user1', 'secret.odt'));
	}
}
