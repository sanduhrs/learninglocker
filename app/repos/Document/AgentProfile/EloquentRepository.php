<?php namespace Repos\Document\AgentProfile;

use \Jenssegers\Mongodb\Eloquent\Builder as Builder;
use \Repos\Document\EloquentRepository as DocumentRepository;

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
    $query = $query->where('documentType', static::$document_type);
    $query = $this->whereAgent($query, $data['agent']);
    $query = $this->whereSince($query, $data['since']);

    return $query;
  }

  protected function constructShowQuery(Builder $query, array $data) {
    $query = $query->where('documentType', static::$document_type);
    $query = $query->where('identId', $data[static::$document_identifier]);
    $query = $this->whereAgent($query, $data['agent']);

    return $query;
  }

  protected function validateDestroy(array $data) {
    if (!isset($data['agent'])) throw new \Exception(
      'Missing agent'
    );
    return parent::validateDestroy($data);
  }
}
