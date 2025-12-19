<?php

namespace App\Providers\Filament;

/**
 * Compatibility provider for tools (like Filament Shield) that expect a
 * provider file named after the panel id (`PainelPanelProvider.php`).
 *
 * This class simply extends the existing AdminPanelProvider implementation
 * so we don't duplicate configuration.
 */
class PainelPanelProvider extends AdminPanelProvider
{
    // Intentionally empty — inherits everything from AdminPanelProvider
}
