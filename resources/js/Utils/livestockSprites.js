// How an animal's drawn in the farm view - set on its mob, or on an animal
// where it differs. Matches Livestock::SPRITES; the farm view's textures
// are made in FarmView/sprites.js (makeAnimalTextures).
export const LIVESTOCK_SPRITES = {
    sheep_white: 'White sheep',
    cow_black: 'Black cow',
};

// The sprite picked for a livestock type's name when none has been set.
// Matches Livestock::spriteForType().
export function spriteForType(type) {
    return /cattle|cow|steer|heifer|bull|calf|calves|\box\b/i.test(type ?? '') ? 'cow_black' : 'sheep_white';
}

// Whether a sprite is one of the cattle (they need more room in a mob).
export const isCattleSprite = (sprite) => sprite?.startsWith('cow_');
