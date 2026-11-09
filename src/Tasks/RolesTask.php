<?php
/**
 * Roles and capabilities.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

namespace Arbe\WPFoundation\Tasks;

use Arbe\WPFoundation\Plugin;

/**
 * Creates custom roles and grants capabilities to existing roles. Both are removed on
 * uninstall, or on deactivation when configured.
 */
final class RolesTask extends AbstractTask {

	/**
	 * Custom roles: role => [display name, capabilities].
	 *
	 * @var array<string, array{name: string, capabilities: array<string, bool>}>
	 */
	private array $roles = array();

	/**
	 * Capabilities to grant: role => capabilities.
	 *
	 * @var array<string, list<string>>
	 */
	private array $grants = array();

	private bool $removeOnDeactivate = false;

	/**
	 * Define a custom role.
	 *
	 * @param string              $role         Role name.
	 * @param string              $displayName  Display name.
	 * @param array<string, bool> $capabilities Capabilities.
	 */
	public function role( string $role, string $displayName, array $capabilities ): void {
		$this->roles[ $role ] = array(
			'name'         => $displayName,
			'capabilities' => $capabilities,
		);
	}

	/**
	 * Grant capabilities to a role.
	 *
	 * @param string       $role         Role name.
	 * @param list<string> $capabilities Capabilities.
	 */
	public function grant( string $role, array $capabilities ): void {
		$this->grants[ $role ] = array_values( array_unique( array_merge( $this->grants[ $role ] ?? array(), $capabilities ) ) );
	}

	/**
	 * Remove roles and capabilities on deactivation as well as uninstall.
	 */
	public function removeOnDeactivate(): void {
		$this->removeOnDeactivate = true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function activate( Plugin $plugin ): void {
		foreach ( $this->roles as $role => $definition ) {
			if ( null === get_role( $role ) ) {
				add_role( $role, $definition['name'], $definition['capabilities'] );
				continue;
			}
			// Role exists from an earlier version: add any new capabilities.
			$existing = get_role( $role );
			foreach ( $definition['capabilities'] as $cap => $grant ) {
				if ( ! $existing->has_cap( $cap ) ) {
					$existing->add_cap( $cap, $grant );
				}
			}
		}

		foreach ( $this->grants as $role => $capabilities ) {
			$object = get_role( $role );
			if ( null === $object ) {
				continue;
			}
			foreach ( $capabilities as $cap ) {
				$object->add_cap( $cap );
			}
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function deactivate( Plugin $plugin ): void {
		if ( $this->removeOnDeactivate ) {
			$this->removeAll();
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function uninstall( Plugin $plugin ): void {
		$this->removeAll();
	}

	/**
	 * Remove granted capabilities and custom roles.
	 */
	private function removeAll(): void {
		foreach ( $this->grants as $role => $capabilities ) {
			$object = get_role( $role );
			if ( null === $object ) {
				continue;
			}
			foreach ( $capabilities as $cap ) {
				$object->remove_cap( $cap );
			}
		}

		foreach ( array_keys( $this->roles ) as $role ) {
			remove_role( $role );
		}
	}
}
