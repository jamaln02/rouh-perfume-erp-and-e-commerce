<?php

namespace Tests\Unit;

use App\Services\ConsumptionRuleService;
use PHPUnit\Framework\TestCase;

class ConsumptionRuleServiceTest extends TestCase
{
    public function test_residual_alcohol_uses_the_project_numeric_business_rule(): void
    {
        $service = new ConsumptionRuleService();

        self::assertSame(34.0, $service->calculateResidualAlcoholMl(50, 16));
    }

    public function test_residual_alcohol_never_goes_negative(): void
    {
        $service = new ConsumptionRuleService();

        self::assertSame(0.0, $service->calculateResidualAlcoholMl(50, 60));
    }
}
