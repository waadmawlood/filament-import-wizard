<?php

namespace Waad\FilamentImportWizard\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Session;

class ImportWizardController extends Controller
{
    public function __invoke(Request $request)
    {
        Session::start();

        $modelClass = $request->get('model');
        $file = $request->get('file');

        return view('filament-import-wizard::page', [
            'modelClass' => $modelClass,
            'file' => $file,
        ]);
    }
}
