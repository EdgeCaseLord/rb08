<?php
// app/Http/Controllers/DashboardController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Analysis;
use App\Models\Recipe;

class DashboardController extends Controller
{
    public function index()
    {
        // Fetch stats data from models
        $stats = [
            'patients' => [
                'label' => 'Patienten',
                'value' => User::patients()->count(),
                'icon' => 'M12 4.5v1.5m0 14.5v1.5m7.5-7.5h1.5m-14.5 0H4.5', // User icon
            ],
            'analyses' => [
                'label' => 'Analysen',
                'value' => Analysis::count(),
                'icon' => 'M21 21l-4.35-4.35m2.85-5.65a7 7 0 11-14 0 7 7 0 0114 0z', // Magnifying glass
            ],
            'recipes' => [
                'label' => 'Rezepte',
                'value' => Recipe::count(),
                'icon' => 'M9 12h6m-3-3v6m-9 3h18a2 2 0 002-2V6a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z', // Document
            ],
            'doctors' => [
                'label' => 'Ärzte',
                'value' => User::doctors()->count(),
                'icon' => 'M12 4.5v1.5m0 14.5v1.5m7.5-7.5h1.5m-14.5 0H4.5', // User-plus
            ],
        ];

        // Fetch recent analyses (limited to 5 for display)
        $analyses = Analysis::with('patient')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($analysis) {
                return [
                    'patient_number' => $analysis->patient_number ?? 'PAT' . str_pad($analysis->id, 3, '0', STR_PAD_LEFT),
                    'patient_name' => $analysis->patient->name ?? 'Unknown',
                    'assay_date' => $analysis->assay_date->format('M d, Y'),
                    'evaluation' => $analysis->evaluation ? $analysis->evaluation->format('M d, Y') : 'Pending',
                    'edit_url' => route('analyses.edit', $analysis), // Adjust route as needed
                ];
            });

        // Static links (replace URLs with actual routes if needed)
        $handbuchLinks = [
            ['title' => 'Patient anlegen', 'url' => route('patients.create')],
            ['title' => 'Arzt anlegen', 'url' => route('doctors.create')],
            ['title' => 'Analyse durchführen', 'url' => route('analyses.create')],
            ['title' => 'Benutzer verwalten', 'url' => route('users.index')],
        ];

        $importantLinks = [
            ['title' => 'Support kontaktieren', 'url' => '#'],
            ['title' => 'Verbesserungsvorschläge', 'url' => '#'],
            ['title' => 'Website IFM-Institut', 'url' => 'https://ifm-institut.de'],
            ['title' => 'Profil verwalten', 'url' => route('profile.edit')],
            ['title' => 'Datenschutz', 'url' => '#'],
        ];

        return view('dashboard', compact('stats', 'analyses', 'handbuchLinks', 'importantLinks'));
    }
}
