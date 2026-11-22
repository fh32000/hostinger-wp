<?php

if (! function_exists('sapa_add_language_to_menu_storage_key')) {
    function sapa_add_language_to_menu_storage_key( $storage_key )
    {
      global $sitepress;

      return $storage_key . '-' . $sitepress->get_current_language();
    }
}
add_filter( 'sapa_menu_storage_key', 'sapa_add_language_to_menu_storage_key', 10, 1 );