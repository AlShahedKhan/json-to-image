<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // Import Log facade
use thiagoalessio\TesseractOCR\TesseractOCR; // Correct namespace for TesseractOCR

class JsonExtractionController extends Controller
{
    /**
     * Handle the POST request to extract JSON from an uploaded image.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function extractJsonFromBase64Image(Request $request)
    {
        // Log the incoming request
        Log::info('Received request for JSON extraction', ['request_data' => $request->all()]);

        // Validate that the image is uploaded
        $validated = $request->validate([
            'imageBase64' => 'required|file|mimes:jpeg,png,jpg',
        ]);

        // Log the validation success
        Log::info('Validation passed for uploaded image');

        // Get the uploaded file
        $imageFile = $request->file('imageBase64');

        if (!$imageFile) {
            Log::error('No file uploaded');
            return response()->json([
                'success' => false,
                'message' => 'No image file uploaded',
            ], 400);
        }

        // Convert the uploaded image file to base64
        $imageData = base64_encode(file_get_contents($imageFile->getRealPath()));

        // Log the length of the base64-encoded image
        Log::info('Image file successfully converted to base64', ['image_length' => strlen($imageData)]);

        // Save the image to a temporary file
        $tempPath = storage_path('app/temp_image.png');
        try {
            file_put_contents($tempPath, base64_decode($imageData));
            Log::info('Image saved to temporary path', ['temp_path' => $tempPath]);
        } catch (\Exception $e) {
            Log::error('Error saving image to file', ['exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error saving image to file',
            ], 400);
        }

        // Run Tesseract OCR to extract text from the image
        try {
            // Explicitly set the Tesseract executable path if it's not in your PATH
            $ocr = new TesseractOCR($tempPath);
            $ocr->executable('C:\\Program Files\\Tesseract-OCR\\tesseract.exe'); // Specify full path to Tesseract if needed
            $extractedText = $ocr->run();
            Log::info('OCR extraction successful', ['extracted_text' => substr($extractedText, 0, 100) . '...']); // Log first 100 chars of the text
        } catch (\Exception $e) {
            Log::error('Error running OCR on image', ['exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error running OCR on image',
            ], 400);
        }

        // Parse the extracted text for JSON
        $jsonData = $this->parseJsonFromText($extractedText);

        if ($jsonData) {
            Log::info('JSON data successfully extracted from text', ['json_data' => $jsonData]);
            return response()->json([
                'success' => true,
                'data' => $jsonData,
                'message' => 'Successfully extracted JSON from image',
            ], 200);
        }

        Log::error('Failed to extract valid JSON from image', ['extracted_text' => $extractedText]);
        return response()->json([
            'success' => false,
            'message' => 'Failed to extract valid JSON from image',
        ], 400);
    }

    /**
     * Parse the extracted text and return it as structured JSON.
     *
     * @param string $text
     * @return array|null
     */
    private function parseJsonFromText($text)
    {
        Log::info('Parsing text for structured data', ['text' => substr($text, 0, 100) . '...']); // Log the first 100 chars of the extracted text

        // Refine the regular expression to clean up key-value pairs
        $patterns = [
            '/name\s*[:|-]?\s*"([^"]+)"/' => 'name',
            '/organization\s*[:|-]?\s*"([^"]+)"/' => 'organization',
            '/address\s*[:|-]?\s*"([^"]+)"/' => 'address',
            '/mobile\s*[:|-]?\s*"([^"]+)"/' => 'mobile',
        ];

        $structuredData = [];

        foreach ($patterns as $pattern => $field) {
            if (preg_match($pattern, $text, $matches)) {
                $structuredData[$field] = $matches[1];
            }
        }

        // Return the structured data if found
        if (!empty($structuredData)) {
            Log::info('Successfully extracted structured data', ['structured_data' => $structuredData]);
            return $structuredData;
        }

        // If no structured data found
        Log::error('Failed to parse structured data from text', ['extracted_text' => $text]);
        return null;
    }
}
