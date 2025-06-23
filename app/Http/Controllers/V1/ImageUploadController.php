<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Services\FileUploadService;
use App\Utilities\ResponseHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ImageUploadController extends Controller
{
    protected FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Upload an image for blog posts
     * Frontend endpoint: POST /api/upload/image
     */
    public function uploadBlogImage(Request $request): JsonResponse
    {
        $rules = [
            'image' => 'required|image|mimes:jpeg,png,webp,gif|max:5120', // 5MB max
            'alt_text' => 'sometimes|string|max:255',
        ];

        $validator = validator($request->all(), $rules);

        if ($validator->fails()) {
            return ResponseHandler::error('Validation failed', 422, 50, $validator->errors());
        }

        try {
            if (!$request->hasFile('image')) {
                return ResponseHandler::error('No image file provided', 400, 51);
            }

            $imageData = $this->fileUploadService->uploadImage(
                $request->file('image'),
                'blogs',
                ['width' => 1200, 'height' => 630]
            );

            if (!$imageData) {
                return ResponseHandler::error('Failed to upload image', 500, 52);
            }

            // **qode** Frontend-compatible response format
            $response = [
                'path' => $imageData['path'],
                'url' => $imageData['url'],
                'filename' => $imageData['filename'],
                'original_name' => $imageData['original_name'],
                'size' => $imageData['size'],
                'mime_type' => $imageData['mime_type'],
                'alt_text' => $request->input('alt_text', ''),
                // Legacy fields for backward compatibility
                'image_path' => $imageData['path'],
                'image_url' => $imageData['url'],
            ];

            return ResponseHandler::success($response, 'Image uploaded successfully');

        } catch (\Exception $e) {
            return ResponseHandler::error('Image upload failed: ' . $e->getMessage(), 500, 53);
        }
    }

    /**
     * Delete an uploaded image
     */
    public function deleteImage(Request $request): JsonResponse
    {
        $rules = [
            'image_path' => 'required|string',
        ];

        $validator = validator($request->all(), $rules);

        if ($validator->fails()) {
            return ResponseHandler::error('Validation failed', 422, 54, $validator->errors());
        }

        try {
            $imagePath = $request->input('image_path');
            $deleted = $this->fileUploadService->deleteFile($imagePath);

            if ($deleted) {
                return ResponseHandler::success([], 'Image deleted successfully');
            } else {
                return ResponseHandler::error('Failed to delete image', 500, 55);
            }

        } catch (\Exception $e) {
            return ResponseHandler::error('Image deletion failed: ' . $e->getMessage(), 500, 56);
        }
    }
}
