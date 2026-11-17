import { createApp } from 'vue';
import GameImageEditor from './components/game-image/GameImageEditor.vue';

const root = document.querySelector<HTMLElement>('[data-game-image-editor]');
if (root) {
    createApp(GameImageEditor, {
        account: JSON.parse(root.dataset.account ?? '{}'),
        demoBase: root.dataset.demoBase ?? '',
        indexUrl: root.dataset.indexUrl ?? '/',
        homeUrl: root.dataset.homeUrl ?? '/',
        logoUrl: root.dataset.logoUrl ?? '',
        readOnly: root.dataset.readOnly === 'true',
    }).mount(root);
}
