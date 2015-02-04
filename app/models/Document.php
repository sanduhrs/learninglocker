<?php namespace Models;

use \Jenssegers\Mongodb\Model as Eloquent;
use \Symfony\Component\HttpFoundation\File\UploadedFile;
use \Repos\Document\Files as DocumentFiles;
use \Helpers\Helpers as Helpers;

class Document extends Eloquent {
  protected $collection = 'documentapi';
  protected $hidden = ['_id', 'lrs'];

  /**
   * PUTs the content into the document.
   * @param String $content.
   * @param String $contentType Content's type.
   */
  private function putContent($content, $contentType) {
    switch ($contentType) {
      case 'application/json':
        $this->setSha($this->content);
        $this->overwriteContent(json_decode($content));
        break;
      case 'text/plain':
        $this->setSha($content);
        $this->overwriteContent($content);
        break;
      default: $this->saveDocument($content, $contentType);
    }
    $this->contentType = $contentType;
  }

  /**
   * POSTs the content into the document.
   * @param String $content.
   * @param String $contentType Content's type.
   */
  private function postContent($content, $contentType) {

    if( $this->exists ){
      $decoded_content = json_decode($content);

      // Checks existing content type and incoming content type are both application/json.
      if ($this->contentType !== 'application/json' || $contentType !== 'application/json') {
        throw new \Exception('Both existing content type and incoming content type must be application/json');
      }

      // Checks existing content and incoming content are both JSON.
      if (!is_object($this->content) || !is_object($decoded_content)) {
        throw new \Exception('Both existing content and incoming content must be parsable as JSON in order to use POST');
      }

      // Merges JSON.
      $this->mergeJSONContent($decoded_content, $contentType);
    } else {
      //If document does not already exist, treat as PUT
      $this->putContent($content, $contentType );
    }
  }

  /**
   * Sets the SHA for the document.
   * @param String $content.
   */
  private function setSha($content) {
    $this->sha = '"'.strtoupper(sha1($content)).'"';
  }

  /**
   * Merges JSON content into the document.
   * @param String $content.
   * @param String $contentType Content's type.
   */
  private function mergeJSONContent($content, $contentType) {
    if (!is_object($content)) {
      throw new \Exception(
        'JSON must contain an object at the top level.'
      );
    } else if ($this->contentType !== $contentType) {
      throw new \Exception(
        'JSON document content may not be merged with that of another type'
      );
    }
    $this->content = Helpers::replaceDots(array_merge((array) $this->content, (array) $content));
    $this->setSha(json_encode($this->content));
  }

  /**
   * Overwrites content in the document.
   * @param String $content.
   */
  private function overwriteContent($content) {
    $this->content = Helpers::replaceDots($content);
  }

  /**
   * Saves the document.
   * @param String $content.
   * @param String $contentType Content's type.
   */
  private function saveDocument($content, $contentType) {
    $dir = $this->getContentDir();

    if ($content instanceof UploadedFile) {
      $origname = $content->getClientOriginalName();
      $parts = pathinfo($origname);
      $filename = Str::slug(Str::lower($parts['filename'])).'-'.time().'.'.$parts['extension'];
      $content->move($dir, $filename);
    } else {
      $ext = array_search($contentType, DocumentFiles::$types);

      $filename = time().'_'.mt_rand(0,1000).($ext !== false ? '.'.$ext : '');

      $size = file_put_contents($dir.$filename, $content);

      if ($size === false) throw new \Exception('There was an issue saving the content');
    }

    $this->content = $filename;
    $this->setSha($content);
  }


  /**
   * Sets the content of the document.
   * @param AssocArray $content_info Contains the content and contentType.
   * @param String $method HTTP method used in the request.
   */
  public function setContent($content_info, $method) {
    $content = Helpers::replaceDots($content_info['content']);
    $contentType = $content_info['contentType'];

    $contentTypeArr = explode(";", $contentType);
    if( sizeof($contentTypeArr) >= 1 ){
      $mimeType = $contentTypeArr[0];
    } else {
      $mimeType = $contentType;
    }

    if ($method === 'PUT') {
      $this->putContent($content, $mimeType);
    } else if ($method === 'POST') {
      $this->postContent($content, $mimeType);
    }

    $this->contentType = $mimeType;

  }

  /**
   * Gets the content's directory.
   * Makes a directory if it doesn't already exist.
   * @return String
   */
  public function getContentDir(){
    $dir = base_path().'/uploads/'.$this->lrs.'/documents/';
    if( !file_exists($dir) ){
      mkdir( $dir, 0774, true );
    }

    return $dir;
  }

  /**
   * Gets the documents file path.
   * @return String
   */
  public function getFilePath(){
    return $this->getContentDir() . $this->content;
  }

}
