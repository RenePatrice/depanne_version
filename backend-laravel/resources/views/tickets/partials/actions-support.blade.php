@php
    use App\Domain\Matching\Actions\ReassignTicket;
    use App\Domain\Tickets\Data\TicketState;

    $annulable = $ticket->state->estAnnulable();
    // Un ticket en litige se clôture par la décision du litige, qui règle le séquestre.
    $cloturable = $ticket->state->peutAllerVers(TicketState::CLOTUREE)
        && $ticket->state->etat() !== TicketState::LITIGE_OUVERT;
    $reassignable = ReassignTicket::estReassignable($ticket);
    $litigeOuvert = $ticket->disputes->first(fn ($litige) => $litige->status->isOpen());
    $peutVoirLitiges = auth('admin')->user()?->can('litiges.voir') ?? false;
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

            <button type="button" class="btn btn-outline-secondary btn-sm text-start"
                    data-bs-toggle="collapse" data-bs-target="#form-reassigner"
                    @disabled(! $reassignable)>
                <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
                Réassigner
                @unless ($reassignable)
                    <span class="text-body-secondary">— seulement avant l'arrivée du technicien</span>
                @endunless
            </button>

            @if ($reassignable)
                <form method="POST" action="{{ route('tickets.reassigner', $ticket) }}"
                      class="collapse border rounded-3 p-3" id="form-reassigner">
                    @csrf
                    <p class="small text-body-secondary mb-2">
                        {{ $ticket->technician?->full_name }} est retiré du ticket et ne sera plus sollicité
                        dessus. Le prix redevient une estimation, et le matching reprend avec les
                        techniciens qui n'ont pas encore été sollicités.
                    </p>
                    <label for="motif-reassignation" class="form-label">Motif</label>
                    <textarea id="motif-reassignation" name="motif" rows="2" required minlength="5" maxlength="300"
                              class="form-control mb-2"
                              placeholder="Exemple : technicien injoignable depuis vingt minutes."></textarea>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1"
                               id="impute-reassignation" name="impute_au_technicien">
                        <label class="form-check-label small" for="impute-reassignation">
                            Imputer au technicien — compte dans son taux d'annulation, donc dans son score
                        </label>
                    </div>

                    <button type="submit" class="btn btn-secondary btn-sm">Confirmer la réassignation</button>
                </form>
            @endif

            {{-- Un remboursement se décide sur une réclamation, jamais en marge :
                 c'est là que le séquestre se règle et que la décision est motivée. --}}
            @if ($litigeOuvert !== null && $peutVoirLitiges)
                <a href="{{ route('litiges.detail', $litigeOuvert) }}"
                   class="btn btn-outline-secondary btn-sm text-start">
                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>
                    Rembourser — trancher le litige {{ $litigeOuvert->reference }}
                </a>
            @else
                <button type="button" class="btn btn-outline-secondary btn-sm text-start" disabled>
                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>
                    Rembourser
                    <span class="text-body-secondary">
                        — {{ $litigeOuvert === null ? 'aucune réclamation en cours sur ce ticket' : 'réservé au traitement des litiges' }}
                    </span>
                </button>
            @endif
        </div>

        <p class="text-body-secondary mt-3 mb-0" style="font-size:.75rem">
            Chaque action passe par le domaine, journalise une transition dans le déroulé
            ci-contre et laisse une trace dans le journal d'audit.
        </p>
    @endunless
</div>
