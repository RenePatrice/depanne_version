import CarteLive from './components/CarteLive';
import EditeurZones from './components/EditeurZones';
import FileValidation from './components/FileValidation';
import FluxActivite from './components/FluxActivite';
import HorlogeConakry from './components/HorlogeConakry';

/**
 * Registre des îlots React montables depuis Blade, via
 * <div data-react-component="NomDuComposant" data-props="{…}">.
 *
 * Les quatre îlots du back-office sont livrés. Les prochains viendront avec
 * l'application mobile (bloc D), qui n'utilise pas ce registre.
 */
export const islands = {
    CarteLive,
    EditeurZones,
    FileValidation,
    FluxActivite,
    HorlogeConakry,
};
