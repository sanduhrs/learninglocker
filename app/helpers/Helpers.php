<?php namespace Helpers;

class Helpers {
  static function getEnvironment($config, $givenHost) {
    foreach ($config as $environment => $hosts) {
      foreach ($hosts as $host) {
        if (str_is($host, $givenHost)) return $environment;
      }
    }
  }

  static function getCurrentDate() {
    $current_date = \DateTime::createFromFormat('U.u', sprintf('%.4f', microtime(true)));
    $current_date->setTimezone(new \DateTimeZone(\Config::get('app.timezone')));
    return $current_date->format('Y-m-d\TH:i:s.uP');
  }

  static function getAgentIdentifier(\stdClass $actor) {
    if (isset($actor->mbox)) return 'mbox';
    if (isset($actor->account)) return 'account';
    if (isset($actor->openid)) return 'openid';
    if (isset($actor->mbox_sha1sum)) return 'mbox_sha1sum';
    return null;
  }
}
