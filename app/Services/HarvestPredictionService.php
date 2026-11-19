<?php

namespace App\Services;

use Carbon\Carbon;

final class HarvestPredictionService
{
    /**
     * Predict harvest date and development timeline using the trained ML model.
     *
     * @param Carbon|null $fruitingStartedAt
     * @param string $mushroomKey
     * @param string|null $boxId
     * @param int $flushNumber
     * @param float|null $substrateWeightGrams
     * @return array<string, mixed>|null
     */
    public static function predict(
        ?Carbon $fruitingStartedAt,
        string $mushroomKey,
        ?string $boxId = null,
        int $flushNumber = 1,
        ?float $substrateWeightGrams = null
    ): ?array {
        if ($fruitingStartedAt === null) {
            return null;
        }

        return MlHarvestPredictionModel::predict(
            $fruitingStartedAt,
            $mushroomKey,
            $boxId,
            $flushNumber,
            $substrateWeightGrams
        );
    }
}
