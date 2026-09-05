@php
    use App\Domain\Tickets\Data\TicketState;

    $annulable = $ticket->state->estAnnulable();
    $cloturable = $ticket->state->peutAllerVers(TicketState::CLOTUREE);
@endphp

<div class="dm-card p-4">
    <h3 class="h6 text-uppercase text-body-secondary mb-3" style="letter-spacing:.08em">Actions support</h3>

    @unless ($peutAgir)
        <p class="text-body-secondary small mb-0">
            Ton rôle permet de consulter ce ticket, pas d'agir dessus.
        </p>
    @else
        <div class="d-flex flex-column gap-2">

            <button type="button" class="btn btn-outline-danger btn-sm text-start"
                    data-bs-toggle="collapse" data-bs-target="#form-annuler"
                    @disabled(! $annulable)>
                <i class="bi bi-x-circle me-1" aria-hidden="true"></i>
                Annuler le ticket
                @unless ($annulable)
                    <span class="text-body-secondary">— impossible en {{ $ticket->state->label() }}</span>
                @endunless
            </button>

            @if ($annulable)
                <form method="POST" action="{{ route('tickets.annuler', $ticket) }}"
                      class="collapse border rounded-3 p-3" id="form-annuler">
                    @csrf
                    <label for="motif-annulation" class="form-label">Motif</label>
                    <textarea id="motif-annulation" name="motif" rows="2" required minlength="5" maxlength="300"
                              class="form-control mb-2"
                              placeholder="Exemple : le client a résolu le problème lui-même."></textarea>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1"
                               id="impute" name="impute_au_technicien">
                        <label class="form-check-label small" for="impute">
                            Imputer au technicien — compte dans son taux d'annulation, donc dans son score
                        </label>
                    </div>

                    <button type="submit" class="btn btn-danger btn-sm">Confirmer l'annulation</button>
                </form>
            @endif

            <button type="button" class="btn btn-outline-secondary btn-sm text-start"
                    data-bs-toggle="collapse" data-bs-target="#form-cloturer"
                    @disabled(! $cloturable)>
                <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>
                Forcer la clôture
                @unless ($cloturable)
                    <span class="text-body-secondary">— impossible en {{ $ticket->state->label() }}</span>
                @endunless
            </button>

            @if ($cloturable)
                <form method="POST" action="{{ route('tickets.cloturer', $ticket) }}"
                      class="collapse border rounded-3 p-3" id="form-cloturer">
                    @csrf
                    <label for="motif-cloture" class="form-label">Motif</label>
                    <textarea id="motif-cloture" name="motif" rows="2" required minlength="5" maxlength="300"
                              class="form-control mb-2"
                              placeholder="Exemple : le client ne valide pas et la libération automatique a échoué."></textarea>
                    <button type="submit" class="btn btn-secondary btn-sm">Confirmer la clôture</button>
                </form>
            @endif

            {{-- Ces deux actions dépendent de blocs qui ne sont pas encore livrés :
                 les afficher grisées est plus honnête que de les masquer. --}}
            <button type="button" class="btn btn-outline-secondary btn-sm text-start" disabled>
                <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
                Réassigner <span class="dm-badge dm-badge--tech ms-1">phase C3</span>
            </button>

            <button type="button" class="btn btn-outline-secondary btn-sm text-start" disabled>
                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>
                Rembourser <span class="dm-badge dm-badge--tech ms-1">phase C5</span>
            </button>
        </div>

        <p class="text-body-secondary mt-3 mb-0" style="font-size:.75rem">
            Chaque action passe par le domaine, journalise une transition dans le déroulé
            ci-contre et laisse une trace dans le journal d'audit.
        </p>
    @endunless
</div>
