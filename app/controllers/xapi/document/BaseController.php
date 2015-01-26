<?php namespace Controllers\XAPI\Document;

use \Controllers\XAPI\BaseController as XAPIController;

abstract class DocumentController extends BaseController {

  // Defines properties to be set by sub classes.
  protected $identifier = '', $required = [], $optional = [], $document_type = '';

  protected function getIndexData($additional = []) {
    return $this->checkParams(
      array_slice($this->required, 0, -1),
      array_merge($this->optional, $additional),
      $this->params
    );
  }

  protected function getShowData() {
    return $this->checkParams(
      $this->required,
      $this->optional,
      $this->params
    );
  }

  /**
   * Completes deletion of $data.
   * @param mixed $data
   * @param boolean $singleDelete determines if deleting multiple objects.
   * @return Response
   */
  protected function completeDelete($data = null, $singleDelete = false) {
    // Attempts to delete the document.
    $success = $this->document->delete(
      $this->lrs->_id,
      $this->document_type,
      $data ?: $this->getShowData(),
      $singleDelete
    );

    if ($success) {
      return \Response::json(['ok'], 204);
    } else {
      return BaseController::errorResponse();
    }
  }

  /**
   * Retrieves attached file content
   * @param string $name Field name
   * @return Array
   */
  public function getAttachedContent($name='content') {
    if (\LockerRequest::hasParam('method') || $this->method === 'POST') {
      return $this->getPostContent($name);
    } else {
      $contentType = \LockerRequest::header('Content-Type', 'text/plain');

      return [
        'content' => \LockerRequest::getContent(),
        'contentType' => $contentType
      ];
    }
  }

  /**
   * Checks for files, then retrieves the stored param.
   * @param String $name Field name
   * @return Array
   */
  public function getPostContent($name){
    if (\Input::hasFile($name)) {
      $content = \Input::file($name);
      $contentType = $content->getClientMimeType();
    } else if (\LockerRequest::getContent()) {
      $content = \LockerRequest::getContent();

      $contentType = \LockerRequest::header('Content-Type');
      $isForm = $this->checkFormContentType($contentType);

      if( !$contentType || $isForm ){
        $contentType = is_object(json_decode($content)) ? 'application/json' : 'text/plain';
      }

    } else {
      \App::abort(400, sprintf('`%s` was not sent in this request', $name));
    }

    return [
      'content' => $content,
      'contentType' => $contentType
    ];
  }

  /**
   * Determines if $contentType is a form.
   * @param string $contentType
   * @return boolean
   */
  private function checkFormContentType($contentType = '') {
    if (!is_string($contentType)) return false;
    return in_array(explode(';', $contentType)[0], [
      'multipart/form-data',
      'application/x-www-form-urlencoded'
    ]);
  }

  /**
   * Generates content response.
   * @param mixed $data used to select the Document.
   * @return Response
   */
  public function documentResponse($data) {
    $document = $this->document->find($this->lrs->_id, $this->document_type, $data);

    if (!$document) {
      return BaseController::errorResponse(null, 404);
    } else {
      $headers = [
        'Updated' => $document->updated_at->toISO8601String(),
        'Content-Type' => $document->contentType,
        'ETag' => $document->sha
      ];

      if( $this->method === 'HEAD' ){ //Only return headers
        return \Response::make(null, 200, $headers);
      } else {
        switch ($document->contentType) {
          case "application/json":
            return \Response::json($document->content, 200, $headers);
          case "text/plain":
            return \Response::make($document->content, 200, $headers);
          default:
            return \Response::download(
              $document->getFilePath(),
              $document->content,
              $headers
            );
        }
      }
    }
  }

  /**
   * Checks and filters $data against $required and $optional parameters.
   * @param AssocArray[Key=>Type] $required
   * @param AssocArray[Key=>Type] $optional
   * @param mixed $data Data
   * @return AssocArray Filtered data.
   */
  public function checkParams($required = [], $optional = [], $data = null) {
    $return_data = [];

    if (is_null($data)) {
      $data = $this->params;
    }

    // Checks required parameters.
    foreach ($required as $name => $expectedType) {
      if (!isset($data[$name])) {
        throw new \Exception('Required parameter is missing - ' . $name);
      } else if ($expectedType !== null) {
        $return_data[$name] = $this->requiredValue($name, $data[$name], $expectedType);
      } else {
        $return_data[$name] = $data[$name];
      }
    }

    // Checks optional parameters.
    foreach ($optional as $name => $expectedType) {
      if (!isset($data[$name])) {
        $return_data[$name] = null;
      } else if ($expectedType !== null) {
        $return_data[$name] = $this->optionalValue($name, $data[$name], $expectedType);
      } else {
        $return_data[$name] = $data[$name];
      }
    }

    return $return_data;
  }

  /**
   * Checks and gets the updated header.
   * @return String The updated timestamp ISO 8601 formatted.
   */
  public function getUpdatedValue() {
    $updated = \LockerRequest::header('Updated');

    // Checks the updated parameter.
    if (!empty($updated)) {
      if (!$this->validateTimestamp($updated)) {
        \App::abort(400, sprintf(
          "`%s` is not an valid ISO 8601 formatted timestamp",
          $updated
        ));
      }
    } else {
      $updated = Carbon::now()->toISO8601String();
    }

    return $updated;
  }

  /**
   * Check that $value is $expected_types.
   */
  public function checkTypes($name, $value, $expected_types) {
    // Convert expected type string into array
    $expected_types = (is_string($expected_types)) ? [$expected_types] : $expected_types;

    $validators = array_map(function ($expectedType) use ($name, $value) {
      $validator = new \app\locker\statements\xAPIValidation();
      $validator->checkTypes($name, $value, $expectedType, 'params');
      return $this->jsonParam($expectedType, $value);
    }, $expected_types);

    $passes = array_filter($validators, function (\app\locker\statements\xAPIValidation $validator) {
      return $validator->getStatus() === 'passed';
    });

    if (count($passes) < 1) {
      \App::abort(400, sprintf(
        "`%s` is not a %s",
        $name,
        implode(',', $expected_types)
      ));
    }

    return $value;
  }
}