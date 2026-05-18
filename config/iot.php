<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ESP32-CAM stream URL
    |--------------------------------------------------------------------------
    | Set this to the URL shown in Serial Monitor after uploading the
    | CameraWebServer sketch, with :81/stream for the MJPEG stream.
    | Must use port 81 for the MJPEG path (not :80). Example: http://192.168.100.150:81/stream
    */
    'camera_stream_url' => env('ESP32_CAM_STREAM_URL', ''),
];
