import { createRoot } from 'react-dom/client';
import { islands } from './registry';

/**
 * Monte chaque îlot React déclaré dans le Blade.
 * Les props sont passées en JSON via l'attribut data-props.
 */
export function mountReactIslands(root = document) {
    root.querySelectorAll('[data-react-component]').forEach((el) => {
        const name = el.dataset.reactComponent;
        const Component = islands[name];

        if (!Component) {
            console.warn(`Îlot React inconnu : « ${name} ». Vérifie resources/js/react/registry.js.`);
            return;
        }

        const props = el.dataset.props ? JSON.parse(el.dataset.props) : {};
        createRoot(el).render(<Component {...props} />);
    });
}
