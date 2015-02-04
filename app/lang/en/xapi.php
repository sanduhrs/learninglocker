<?php

return [
  "errors" => [
    "both_ids" => "You can't request based on both :statement_id and :voided_id.",
    "id_set" => ":statement_id parameter should not be set.",
    "json" => ":value is not valid JSON.",
    "formatting" => "There is a problem with the formatting of your submitted content.",
    "no_attachment" => "There were no attachments.",
    "required" => "`:field` is required but not set.",
    "void_voider" => "Cannot void a voiding statement",
    "void_existence" => "Cannot a void a statement that does not exist.",
    "unset_param" => ":field was not sent in this request.",
    "missing_account_params" => "Missing required paramaters in the agent.account",
    "missing_agent" => "Missing required paramaters in the agent",
    "check_state" => "Check the current state of the resource then set the `If-Match` header with the current ETag to resolve the conflict.",
    "multi_delete" => "Multiple document DELETE not permitted"
  ]
];
