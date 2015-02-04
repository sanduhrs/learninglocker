<?php namespace Helpers;

use \IlluminateRequest as IlluminateRequest;
use \Locker\XApi\Atom as XAPIAtom;
use \Locker\XApi\Errors\Error as XAPIError;

class Helpers {
  /**
   * Gets the current environment.
   * @param AssocArray $config Hosts mapped to environments.
   * @param String $givenHost Host in use.
   * @return String Enviroment for the host.
   */
  static function getEnvironment($config, $givenHost) {
    foreach ($config as $environment => $hosts) {
      foreach ($hosts as $host) {
        if (str_is($host, $givenHost)) return $environment;
      }
    }
  }

  /**
   * Gets the current date and time in ISO format using the current timezone.
   * @return String Current ISO date and time.
   */
  static function getCurrentDate() {
    $current_date = \DateTime::createFromFormat('U.u', sprintf('%.4f', microtime(true)));
    $current_date->setTimezone(new \DateTimeZone(\Config::get('app.timezone')));
    return $current_date->format('Y-m-d\TH:i:s.uP');
  }

  /**
   * Determines which identifier is currently in use in the given actor.
   * @param \stdClass $actor.
   * @return String|null Identifier in use.
   */
  static function getAgentIdentifier(\stdClass $actor) {
    if (isset($actor->mbox)) return 'mbox';
    if (isset($actor->account)) return 'account';
    if (isset($actor->openid)) return 'openid';
    if (isset($actor->mbox_sha1sum)) return 'mbox_sha1sum';
    return null;
  }

  /**
   * Generates a new UUID.
   * @return String
   */
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

  /**
   * Gets the CORS headers.
   * @return AssocArray CORS headers.
   */
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

  /**
   * Validates a XAPIAtom.
   * @param XAPIAtom $atom Atom to be validated.
   * @param String $trace Where the atom has came from (i.e. request parameter name).
   */
  static function validateAtom(XAPIAtom $atom, $trace = null) {
    $errors = $atom->validate();
    if (count($errors) > 0) throw new \Exception(json_encode(array_map(function (XAPIError $error) use ($trace) {
      return (string) ($trace === null ? $error : $error->addTrace($trace));
    }, $errors)));
  }

  /**
   * Replaces all of the dots in an value with "HTML dots" for storage in Mongo.
   * @param mixed $value
   * @return mixed
   */
  static function replaceDots($value) {
    return json_decode(
      str_replace('.', '&46;', json_encode($value))
    );
  }

  /**
   * Replaces all of the "HTML dots" in an value with dots for retrieval from Mongo.
   * @param mixed $value
   * @return mixed
   */
  static function replaceHTMLDots($value) {
    return json_decode(
      str_replace('&46;', '.', json_encode($value))
    );
  }
}
