<?php namespace Repos\Document\AgentProfile;

use \Jenssegers\Mongodb\Eloquent\Builder as Builder;
use \Repos\Document\EloquentRepository as DocumentRepository;
use \Helpers\Helpers as Helpers;

class EloquentRepository extends DocumentRepository {
  protected static $document_type = 'agentProfile';
  protected static $document_identifier = 'profileId';

  protected static $ap_props = [
    'profileId',
    'agent',
  ];
  protected static $data_props = [
    'profileId' => null,
    'agent' => null,
    'since' => null,
    'content_info' => null,
    'ifMatch' => null,
    'ifNoneMatch' => null,
    'method' => 'POST',
    'updated' => null
  ];

  protected function constructIndexQuery(Builder $query, array $data) {
    if (!isset($data['agent'])) throw new \Exception(
      'Missing agent'
    );
    $query = $query->where('documentType', static::$document_type);
    $query = $this->whereAgent($query, $data['agent']);
    $query = $this->whereSince($query, $data['since']);

    return $query;
  }

  protected function constructShowQuery(Builder $query, array $data) {
    if (!isset($data['agent'])) throw new \Exception(
      'Missing agent'
    );
    if (!isset($data[static::$document_identifier])) throw new \Exception(
      'Missing '.static::$document_identifier
    );

    $query = $query->where('documentType', static::$document_type);
    $query = $query->where(static::$document_identifier, $data[static::$document_identifier]);
    $query = $this->whereAgent($query, $data['agent']);

    return $query;
  }

  protected function validateData(array $data) {
    if ($data['agent'] !== null) Helpers::validateAtom(new \Locker\XApi\Agent($data['agent']));
    if ($data['profileId'] !== null) Helpers::validateAtom(new \Locker\XApi\String($data['profileId']));
    if ($data['since'] !== null) Helpers::validateAtom(new \Locker\XApi\Timestamp($data['since']));
    if ($data['ifMatch'] !== null) Helpers::validateAtom(new \Locker\XApi\Sha1($data['ifMatch']));
    if ($data['ifNoneMatch'] !== null && $data['ifNoneMatch'] !== '*') Helpers::validateAtom(new \Locker\XApi\Sha1($data['ifNoneMatch']));
    if ($data['updated'] !== null) Helpers::validateAtom(new \Locker\XApi\Timestamp($data['updated']));
    if ($data['method'] !== null) Helpers::validateAtom(new \Locker\XApi\String($data['method']));
  }
}
