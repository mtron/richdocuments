<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Richdocuments\Service;

use OCA\Richdocuments\AppInfo\Application;
use OCA\Richdocuments\Db\Wopi;
use OCA\Richdocuments\PermissionManager;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IConfig;
use OCP\Share\IShare;

/**
 * Decides whether Collabora's "Open locally" action is allowed for a file.
 *
 * Secure View (including system-tag rules such as Personalplanung) and
 * hide-download shares must not be openable via the desktop client.
 */
class OpenLocalPolicyService {
	public function __construct(
		private IConfig $config,
		private PermissionManager $permissionManager,
	) {
	}

	public function isGloballyEnabled(): bool {
		return $this->config->getAppValue(Application::APPNAME, 'open_local_editor', 'yes') === 'yes';
	}

	public function isAllowedForFile(Node $file, ?string $userId = null, ?IShare $share = null): bool {
		if (!$this->isGloballyEnabled()) {
			return false;
		}
		if (!$file instanceof File) {
			return false;
		}

		$ownerId = null;
		try {
			$ownerId = $file->getOwner()?->getUID();
		} catch (\Throwable) {
			$ownerId = null;
		}

		// shouldWatermark() covers Secure View tag/group/share rules
		if ($this->permissionManager->shouldWatermark($file, $userId, $share, $ownerId)) {
			return false;
		}

		return true;
	}

	public function isAllowedForWopi(Wopi $wopi, Node $file, ?IShare $share = null): bool {
		if ($wopi->getHideDownload()) {
			return false;
		}
		return $this->isAllowedForFile($file, $wopi->getEditorUid(), $share);
	}

	/**
	 * Resolve a user-relative path (as used by files openlocaleditor) and apply the same policy.
	 *
	 * Viewer "Open locally" mints a token via POST apps/files/api/v1/openlocaleditor with {path};
	 * that endpoint must consult this method so Secure View cannot be bypassed when the
	 * richdocuments /api/v1/local unlock call is skipped or JS is stale.
	 */
	public function isAllowedForUserPath(IRootFolder $rootFolder, string $userId, string $path): bool {
		$userFolder = $rootFolder->getUserFolder($userId);
		$node = $userFolder->get(ltrim($path, '/'));
		return $this->isAllowedForFile($node, $userId, null);
	}
}
