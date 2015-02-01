<?php namespace Helpers;

use \IlluminateRequest as IlluminateRequest;
use \Locker\XApi\Atom as XAPIAtom;
use \Locker\XApi\Errors\Error as XAPIError;

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

  static function makeUUID(){
    $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'LL';
    mt_srand(crc32(serialize([microtime(true), $remote_addr, 'ETC'])));

    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      mt_rand(0, 0xffff), mt_rand(0, 0xffff),
      mt_rand(0, 0xffff),
      mt_rand(0, 0x0fff) | 0x4000,
      mt_rand(0, 0x3fff) | 0x8000,
      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
  }

  static function getCORSHeaders() {
    return [
      'Access-Control-Allow-Origin' => IlluminateRequest::root(),
      'Access-Control-Allow-Methods' => 'GET, PUT, POST, DELETE, OPTIONS',
      'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization, X-Requested-With, X-Experience-API-Version, X-Experience-API-Consistent-Through, Updated',
      'Access-Control-Allow-Credentials' => 'true',
      'X-Experience-API-Consistent-Through' => Helpers::getCurrentDate(),
      'X-Experience-API-Version' => '1.0.1'
    ];
  }

  static function validateAtom(XAPIAtom $atom) {
    $errors = $atom->validate();
    if (count($errors) > 0) throw new \Exception(json_encode(array_map(function (XAPIError $error) {
      return (string) $error;
    }, $errors)));
  }

  static function replaceDots($value) {
    return json_decode(
      str_replace('.', '&46;', json_encode($value))
    );
  }

  static function replaceHTMLDots($value) {
    return json_decode(
      str_replace('&46;', '.', json_encode($value))
    );
  }
}
