<?php

declare(strict_types=1);

namespace OCA\ArbeitszeitCheck\Controller;

use OCP\AppFramework\Http\Attribute\PublicPage;

/** @internal Used by middleware unit tests only. */
final class FakeControllerForMiddlewareTest
{
	public function dashboard(): void
	{
	}

	public function clockIn(): void
	{
	}

	#[PublicPage]
	public function publicPing(): void
	{
	}
}
