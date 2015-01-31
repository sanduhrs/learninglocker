<?php namespace Repos\Document\ActivityProfile;

use \Jenssegers\Mongodb\Eloquent\Builder as Builder;
use \Repos\Document\EloquentRepository as DocumentRepository;

class EloquentRepository extends DocumentRepository {
  protected static $document_type = 'activityProfile';
  protected static $document_identifier = 'profileId';

  protected static $ap_props = [
    'profileId',
    'activityId'
  ];
  protected static $data_props = [
    'profileId' => null,
    'activityId' => null,
    'since' => null,
    'content_info' => null,
    'ifMatch' => null,
    'ifNoneMatch' => null,
    'method' => 'POST',
    'updated' => null
  ];

  protected function constructIndexQuery(Builder $query, array $data) {
    $query = $query->where('documentType', static::$document_type);
    $query = $query->where('activityId', $data['activityId']);
    $query = $this->whereSince($query, $data['since']);

    return $query;
  }

  protected function constructShowQuery(Builder $query, array $data) {
    $query = $query->where('documentType', static::$document_type);
    $query = $query->where('activityId', $data['activityId']);
    $query = $query->where(static::$document_identifier, $data[static::$document_identifier]);

    return $query;
  }
}
