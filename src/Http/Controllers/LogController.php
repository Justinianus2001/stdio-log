<?php

namespace Justinianus\StdioLog\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Justinianus\StdioLog\Helpers\StdioLogHelper;

class LogController extends Controller
{
    public function detail(Request $request)
    {
        $content = File::get(StdioLogHelper::decodeLogID($request->dir));
        dd($content);
    }
}