<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="SaaS API Documentation",
 *     version="1.0.0",
 *     description="API Documentation untuk SaaS Application",
 *     @OA\Contact(
 *         email="support@example.com"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000",
 *     description="Local Development Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="Token",
 *     description="Enter token in format: Bearer {token}"
 * )
 */
abstract class Controller
{
    //
}
