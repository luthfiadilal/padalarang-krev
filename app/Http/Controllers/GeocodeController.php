<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;


class GeocodeController extends Controller
{
    function getCoordinates($kecamatan)
    {
        $apiKey = env('ORS_API_KEY'); // simpan di .env
        $response = Http::get('https://api.openrouteservice.org/geocode/search', [
            'api_key' => $apiKey,
            'text' => $kecamatan,
            'size' => 1
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['features'][0]['geometry']['coordinates'])) {
                return $data['features'][0]['geometry']['coordinates']; // [lon, lat]
            }
        }

        return null;
    }

    function getDistanceKm($coord1, $coord2)
    {
        $apiKey = env('ORS_API_KEY');

        $body = [
            'locations' => [
                $coord1, // [lon1, lat1]
                $coord2, // [lon2, lat2]
            ]
        ];

        $response = Http::withHeaders([
            'Authorization' => $apiKey,
            'Content-Type'  => 'application/json',
        ])->post('https://api.openrouteservice.org/v2/matrix/driving-car', $body);

        if ($response->successful()) {
            $data = $response->json();
            $distanceMeters = $data['distances'][0][1] ?? null;
            return $distanceMeters ? $distanceMeters / 1000 : null; // km
        }

        return null;
    }


}
