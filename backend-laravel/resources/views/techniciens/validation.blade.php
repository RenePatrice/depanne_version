@extends('layouts.backoffice')

@section('titre', 'File de validation')
@section('rubrique', 'Techniciens')

@section('contenu')

    <div class="dm-page-head">
        <div class="flex-grow-1">
            <h2 class="dm-page-head__title">File de validation</h2>
            <p class="dm-page-head__lede">
                Pièce d'identité et selfie côte à côte. Tant qu'un dossier n'est pas approuvé,
                le technicien n'apparaît jamais dans le matching — c'est ce qui fait tenir la
                promesse de « techniciens vérifiés ».
            </p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div data-react-component="FileValidation"
         data-props="{{ json_encode([
             'urlDossiers' => route('techniciens.dossiers'),
             'urlApprouver' => route('techniciens.approuver', ['technicien' => '__ID__']),
             'urlRejeter' => route('techniciens.rejeter', ['technicien' => '__ID__']),
             'urlFiche' => route('techniciens.detail', ['technicien' => '__ID__']),
             'jeton' => csrf_token(),
         ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}">
        <div class="dm-empty">
            <span class="dm-empty__icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
            <p class="dm-empty__text">Chargement des dossiers en attente…</p>
        </div>
    </div>

@endsection
