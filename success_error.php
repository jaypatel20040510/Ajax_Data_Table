<?php


/**
 * Send a success JSON response.
 *
 * @param string $message     Response message
 * @param array  $data        Main response data
 * @param int    $statusCode  HTTP status code
 * @param array  $extra       Extra key-value pairs to merge into response
 *
 * @return string JSON encoded response
 *
 * Example:
 * successResponse(
 *     "Users fetched",
 *     ["users" => $users],
 *     200,
 *     [
 *         "total" => 100,
 *         "page" => 1
 *     ]
 * );
 */
// Success Response Function
function successResponse(
    $message = "Success",
    $data = null,
    $statusCode = 200,
    $extra = []
) {
    http_response_code($statusCode);

    return json_encode([
        "success" => true,
        "message" => $message,
        "data" => $data,

        // spread extra key-value pairs
        ...$extra
    ]);
}



/**
 * Send an error JSON response.
 *
 * @param string $message     Error message
 * @param array  $errors      Validation or error details
 * @param int    $statusCode  HTTP status code
 *
 * @return string JSON encoded response
 *
 * Example:
 * errorResponse(
 *     "Validation failed",
 *     [
 *         "email" => "Email is required"
 *     ],
 *     422
 * );
 */
// Error Response Function
function errorResponse($message = "Something went wrong", $errors = [], $statusCode = 400)
{
    http_response_code($statusCode);

    return json_encode([
        "success" => false,
        "message" => $message,
        "errors" => $errors
    ]);
}

?>