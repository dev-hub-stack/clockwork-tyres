<?php

namespace App\Modules\Procurement\Support;

use App\Modules\Procurement\Enums\ProcurementWorkflowStage;

final class ProcurementWorkflow
{
    /**
     * @return array<int, array{value: string, label: string, terminal: bool}>
     */
    public static function stages(): array
    {
        return array_map(
            fn (ProcurementWorkflowStage $stage) => [
                'value' => $stage->value,
                'label' => $stage->label(),
                'terminal' => $stage->isTerminal(),
            ],
            ProcurementWorkflowStage::ordered()
        );
    }

    /**
     * @return array<int, string>
     */
    public static function stageValues(): array
    {
        return array_map(
            fn (ProcurementWorkflowStage $stage) => $stage->value,
            ProcurementWorkflowStage::ordered()
        );
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (ProcurementWorkflowStage::ordered() as $stage) {
            $labels[$stage->value] = $stage->label();
        }

        return $labels;
    }

    public static function isAdminVisibleStage(ProcurementWorkflowStage $stage): bool
    {
        return ! $stage->isTerminal() || $stage === ProcurementWorkflowStage::CANCELLED;
    }
}
