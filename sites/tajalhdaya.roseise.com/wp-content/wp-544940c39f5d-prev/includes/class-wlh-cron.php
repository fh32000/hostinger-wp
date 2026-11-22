<?php

if (!defined('ABSPATH')) exit;

class WLH_Cron
{
    const EVENT_HEALTH = 'wlh_health_event';
    const SCHEDULE = 'wlh_15min';

    public static function register_hooks()
    {
        add_filter('cron_schedules', array('WLH_Cron', 'add_schedule'));
        add_action(self::EVENT_HEALTH, array('WLH_Cron', 'run_health'));
        if (!wp_next_scheduled(self::EVENT_HEALTH)) {
            self::schedule();
        }
    }

    public static function add_schedule($schedules)
    {
        if (!isset($schedules[self::SCHEDULE])) {
            $schedules[self::SCHEDULE] = array('interval' => 900, 'display' => 'Every 15 minutes');
        }
        return $schedules;
    }

    public static function schedule()
    {
        if (!wp_next_scheduled(self::EVENT_HEALTH)) {
            wp_schedule_event(time() + 60, self::SCHEDULE, self::EVENT_HEALTH);
        }
    }

    public static function unschedule()
    {
        $ts = wp_next_scheduled(self::EVENT_HEALTH);
        if ($ts) {
            wp_unschedule_event($ts, self::EVENT_HEALTH);
        }
    }

    public static function run_health()
    {
        if (!WLH_Client::is_configured()) {
            WLH_Client::try_claim();
            if (!WLH_Client::is_configured()) {
                return;
            }
        }
        WLH_Client::heartbeat();
    }
}
