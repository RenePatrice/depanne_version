@php
    $prefixe = $prestation?->id ?? 'nouvelle';
    $inclus = collect($prestation?->included ?? [])->implode("\n");
    $exclus = collect($prestation?->excluded ?? [])->implode("\n");
@endphp

<div class="row g-2">
    <div class="col-12 col-md-7">
        <label class="form-label" for="p-nom-{{ $prefixe }}">Nom de la prestation</label>
        <input class="form-control" id="p-nom-{{ $prefixe }}" name="name" required maxlength="150"
               value="{{ old('name', $prestation?->name) }}"
               placeholder="Réparation de fuite de robinet">
    </div>

    <div class="col-12 col-md-5">
        <label class="form-label" for="p-cat-{{ $prefixe }}">Catégorie</label>
        <select class="form-select" id="p-cat-{{ $prefixe }}" name="category_id" required>
            @foreach ($categories as $categorie)
                <option value="{{ $categorie->id }}"
                    @selected(old('category_id', $prestation?->category_id) === $categorie->id)>
                    {{ $categorie->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-6 col-md-4">
        <label class="form-label" for="p-prix-{{ $prefixe }}">Prix de référence (GNF)</label>
        <input class="form-control" type="number" min="1000" step="1000" required
               id="p-prix-{{ $prefixe }}" name="base_price_gnf"
               value="{{ old('base_price_gnf', $prestation?->base_price_gnf) }}">
    </div>

    <div class="col-6 col-md-4">
        <label class="form-label" for="p-duree-{{ $prefixe }}">Durée estimée (min)</label>
        <input class="form-control" type="number" min="5" max="1440" step="5" required
               id="p-duree-{{ $prefixe }}" name="estimated_duration_min"
               value="{{ old('estimated_duration_min', $prestation?->estimated_duration_min ?? 60) }}">
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label" for="p-ordre-{{ $prefixe }}">Ordre d'affichage</label>
        <input class="form-control" type="number" min="0" max="999"
               id="p-ordre-{{ $prefixe }}" name="sort_order"
               value="{{ old('sort_order', $prestation?->sort_order ?? 0) }}">
    </div>

    <div class="col-12">
        <label class="form-label" for="p-desc-{{ $prefixe }}">Description</label>
        <textarea class="form-control" rows="2" maxlength="2000"
                  id="p-desc-{{ $prefixe }}" name="description"
                  placeholder="Ce que le client verra sur l'écran de détail.">{{ old('description', $prestation?->description) }}</textarea>
    </div>

    <div class="col-12 col-md-6">
        <label class="form-label" for="p-inclus-{{ $prefixe }}">Compris dans le prix</label>
        <textarea class="form-control" rows="4" maxlength="2000"
                  id="p-inclus-{{ $prefixe }}" name="included"
                  placeholder="Une ligne par élément">{{ old('included', $inclus) }}</textarea>
        <div class="dm-param__aide">Une ligne par élément — affiché tel quel dans l'application.</div>
    </div>

    <div class="col-12 col-md-6">
        <label class="form-label" for="p-exclus-{{ $prefixe }}">Non compris</label>
        <textarea class="form-control" rows="4" maxlength="2000"
                  id="p-exclus-{{ $prefixe }}" name="excluded"
                  placeholder="Une ligne par élément">{{ old('excluded', $exclus) }}</textarea>
        <div class="dm-param__aide">C'est ce qui évite les litiges de surfacturation.</div>
    </div>
</div>

<div class="form-check mt-2">
    <input class="form-check-input" type="checkbox" value="1" name="is_active"
           id="p-actif-{{ $prefixe }}" @checked(old('is_active', $prestation?->is_active ?? true))>
    <label class="form-check-label small" for="p-actif-{{ $prefixe }}">
        Prestation visible dans le catalogue mobile
    </label>
</div>
