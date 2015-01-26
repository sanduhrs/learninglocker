<?php namespace Repos\Document\ActivityProfile;

use \Illuminate\Database\Query\Builder as Builder;
use \Repos\Document\EloquentRepository as DocumentRepository;

class EloquentRepository extends DocumentRepository {
  protected static $DOCUMENT_TYPE = 'activityProfile';
  protected static $DOCUMENT_IDENTIFIER = 'profileId';

  protected static $AP_PROPS = [
    'profileId',
    'activityId'
  ];
  protected static $DATA_PROPS = [
    'profileId' => null,
    'activityId' => null,
    'since' => null,
    'content_info' => null,
    'ifMatch' => null,
    'ifNoneMatch' => null,
    'method' => 'POST'
  ];

  protected function constructIndexQuery(Builder $query, array $data) {
    $query = $query->where('documentType', static::DOCUMENT_TYPE);
    $query = $query->where('activityId', $data['activityId']);
    $query = $this->whereSince($query, $data['since']);

    return $query;
  }

  protected function constructShowQuery(Builder $query, array $data) {
    $query = $query->where('documentType', static::DOCUMENT_TYPE);
    $query = $query->where('activityId', $data['activityId']);
    $query = $query->where('identId', static::DOCUMENT_IDENTIFIER);

    return $query;
  }
}