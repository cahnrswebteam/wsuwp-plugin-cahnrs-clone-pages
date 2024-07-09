<?php namespace CAHNRS\Plugin\ClonePages;

class CAHNRSClonePagesPlugin {

	public static function get( $property ) {

		switch ( $property ) {

			case 'version':
				return CAHNRSCLONEPAGEVERSION;

			case 'dir':
				return plugin_dir_path( dirname( __FILE__ ) );

			default:
				return '';

		}

	}

	public static function init() {

		// Do plugin stuff here
		require_once __DIR__ . '/../classes/class-clone-pages.php';
	}


}

CAHNRSClonePagesPlugin::init();