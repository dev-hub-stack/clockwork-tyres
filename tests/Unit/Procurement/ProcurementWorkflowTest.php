<?php

namespace Tests\Unit\Procurement;

use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Support\ProcurementWorkflow;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcurementWorkflowTest extends TestCase
{
    #[Test]
    public function it_exposes_the_expected_stage_order(): void
    {
        $this->assertSame(
            [
                'draft',
                'submitted',
                'supplier_review',
                'quoted',
                'approved',
                'invoiced',
                'stock_reserved',
                'stock_deducted',
                'fulfilled',
                'cancelled',
            ],
            ProcurementWorkflow::stageValues()
        );
    }

    #[Test]
    public function it_marks_terminal_and_pre_post_approval_stages(): void
    {
        $this->assertTrue(ProcurementWorkflowStage::FULFILLED->isTerminal());
        $this->assertTrue(ProcurementWorkflowStage::CANCELLED->isTerminal());
        $this->assertTrue(ProcurementWorkflowStage::DRAFT->isPreApproval());
        $this->assertTrue(ProcurementWorkflowStage::APPROVED->isPostApproval());
        $this->assertFalse(ProcurementWorkflowStage::SUBMITTED->isTerminal());
    }

    #[Test]
    public function it_returns_labels_for_admin_use(): void
    {
        $labels = ProcurementWorkflow::labels();

        $this->assertSame('Draft', $labels['draft']);
        $this->assertSame('Stock Reserved', $labels['stock_reserved']);
        $this->assertSame('Cancelled', $labels['cancelled']);
    }
}
