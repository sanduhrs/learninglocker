<?php namespace Repos\Document\AgentProfile;

use \Illuminate\Database\Query\Builder as Builder;
use \Repos\Document\EloquentRepository as DocumentRepository;

class EloquentRepository extends DocumentRepository {
  protected static $DOCUMENT_TYPE = 'agentProfile';
  protected static $DOCUMENT_IDENTIFIER = 'profileId';

  protected static $AP_PROPS = [
    'profileId',
    'agent',
  ];
  protected static $DATA_PROPS = [
    'profileId' => null,
    'agent' => null,
    'since' => null,
    'content_info' => null,
    'ifMatch' => null,
    'ifNoneMatch' => null,
    'method' => 'POST'
  ];

  protected function constructIndexQuery(Builder $query, array $data) {
    $query = $query->where('documentType', static::DOCUMENT_TYPE);
    $query = $this->whereAgent($query, $data['agent']);
    $query = $this->whereSince($query, $data['since']);

    return $query;
  }

  protected function constructShowQuery(Builder $query, array $data) {
    $query = $query->where('documentType', static::DOCUMENT_TYPE);
    $query = $query->where('identId', $data[static::DOCUMENT_IDENTIFIER]);
    $query = $this->whereAgent($query, $data['agent']);

    return $query;
  }
}