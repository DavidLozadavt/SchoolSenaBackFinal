<?php

namespace App\Traits;

use InvalidArgumentException;
use App\Enums\PayrollConfigurationStatus;

trait PayrollOperations
{
    /**
     * Get base hour value by basic value payment and hours month ($valor / numHorasMes)
     * @param int $basicValuePayment
     * @param int $hoursMonth
     * @return int
     */
    public function getBaseHourValue(int $basicValuePayment, int $hoursMonth): int
    {
        return $hoursMonth > 0 ? (int) round($basicValuePayment / $hoursMonth) : 0;
    }

    /**
     * Get payroll payment by basic value payment and payday ($valor / 30 * diaPago)
     * @param int $basicValuePayment
     * @param int $payday
     * @return int
     */
    public function getPayrollPayment(int $basicValuePayment, int $payday): int
    {
        return ($basicValuePayment / 30) * $payday;
    }

    /**
     * Get sub total by payroll payment, commissions and overtime value (pagoNominaParcial + comisiones + valorHorasExtras)
     * @param int $payrollPayment
     * @param int $commissions
     * @param int $overtimeValue
     * @return int
     */
    public function getSubTotal(int $payrollPayment, int $commissions = 0, int $overtimeValue = 0): int
    {
        return $payrollPayment + $commissions + $overtimeValue;
    }

    /**
     * Get transportation assistance by minimum wage, basic value payment
     * @param float $minimumWage
     * @param float $basicValuePayment
     * @param int $numAuxTrans
     * @param string $auxTransComparison
     * @param float $valAuxTrans
     * @param int $payday
     * @return int
     */
    public function getTransportationAssistance(
        float $minimumWage,
        float $basicValuePayment,
        int $numAuxTrans,
        string $auxTransComparison,
        float $valAuxTrans,
        int $payday
    ): int {
        // salario minimo * numAuxTrans = valor1
        // si (salarioBasico comparacionAuxTransporte valor1) entonces hallar el auxilio de transporte para el trabajador
        // (valAuxTransporte / 30) * diaPago
        // si no el valor es 0
        $value = $minimumWage * $numAuxTrans;

        $isEligible = $this->evaluateComparison($basicValuePayment, $value, $auxTransComparison);

        if ($isEligible) {
            return (int) (($valAuxTrans / 30) * $payday);
        }

        return 0;
    }

    /**
     * Evaluate comparison between two values
     * @param float $leftValue
     * @param float $rightValue
     * @param string $operator
     * @return bool
     */
    private function evaluateComparison(float $leftValue, float $rightValue, string $operator): bool
    {
        return match ($operator) {
            PayrollConfigurationStatus::MAYOR => $leftValue > $rightValue,
            PayrollConfigurationStatus::IGUAL => $leftValue == $rightValue,
            PayrollConfigurationStatus::MENOR => $leftValue < $rightValue,
            default => throw new InvalidArgumentException('Unknown operator.'),
        };
    }

    /**
     * Sum all values
     * @param array $numbers
     * @throws \InvalidArgumentException
     * @return float|int
     */
    public function sumValues(...$numbers): float|int
    {
        foreach ($numbers as $number) {
            if (!is_int($number) && !is_float($number)) {
                throw new InvalidArgumentException(message: "All values ​​must be int or float.");
            }
        }

        return array_sum($numbers);
    }

    /**
     * Calculate health value by percentage value (subtotal * percentage)
     * @param float $subTotal
     * @param float $percentage
     * @return float
     */
    private function calculatePercentageValue(float $subTotal, float $percentage): float
    {
        return (float) round($subTotal * ($percentage / 100));
    }

    /**
     * Calculate difference after sum by value J, value K and value L (value1 - (value2 + value3))
     * @param float $value1
     * @param float $value2
     * @param float $value3
     * @return float
     */
    private function calculateDifferenceAfterSum(float $value1, float $value2, float $value3): float
    {
        return $value1 - ($value2 + $value3);
    }

    private function getValueFspOrContributionsFsp(
        float $minimumWage,
        float $basicValuePayment,
        float $percentageFsp,
        string $auxFspComparison,
        float $numAuxFsp,
        float $subTotal,
    ): float {
        $value = round($minimumWage * $numAuxFsp);

        $isEligible = $this->evaluateComparison($basicValuePayment, $value, $auxFspComparison);

        if ($isEligible) {
            return (float) round($subTotal * ($percentageFsp / 100));
        }

        return 0;
    }

    /**
     * Calculate weighted sum by value1, value2, value3 and percentage ((value1 + value2 + value3) * percentage)
     * @param float $value1
     * @param float $value2
     * @param float $value3
     * @param float $percentage
     * @return float
     */
    private function calculateWeightedSum(float $value1, float $value2, float $value3, float $percentage): float
    {

        // Validar que el porcentaje esté entre 0 y 100
        if ($percentage < 0 || $percentage > 100) {
            throw new InvalidArgumentException('The percentage must be between 0 and 100.');
        }

        // Convertir el porcentaje a decimal
        $decimalPercentage = $percentage / 100;

        return ($value1 + $value2 + $value3) * $decimalPercentage;
    }

    /**
     * Multiply all values
     * @param array $numbers
     * @throws \InvalidArgumentException
     * @return float|int
     */
    private function multiplyValues(...$numbers): float|int
    {
        foreach ($numbers as $number) {
            if (!is_int($number) && !is_float($number)) {
                throw new InvalidArgumentException("Todos los valores deben ser int o float.");
            }
        }

        $result = 1;

        foreach ($numbers as $number) {
            $result *= $number;
        }

        return $result;
    }

    /**
     * Calculate total by val1, val2, val3 and val4 ((val1 + val2 + val3) * val4)
     * @param float $val1
     * @param float $val2
     * @param float $val3
     * @param float $val4
     * @return float
     */
    public function calculateSumAndMultiply(float $val1, float $val2, float $val3, float $val4): float
    {
        return ($val1 + $val2 + $val3) * $val4;
    }
}
