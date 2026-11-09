<?php
/**
 * Fixture table.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tests\Integration\Fixtures;

use Bignall\WPFoundation\Database\Table;

final class ItemsTable extends Table {

	public function __construct( private bool $withNotes = false ) {}

	public function name(): string {
		return 'wpf_items';
	}

	public function columns(): string {
		$notes = $this->withNotes ? "notes text NULL,\n" : '';

		return "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
title varchar(191) NOT NULL DEFAULT '',
{$notes}created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY created_at (created_at)";
	}
}
