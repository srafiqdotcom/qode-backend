<?php


namespace App\Utilities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FilterHelper
{
    /**
     * Apply filters to the query builder.
     *
     * @param Builder $query The query builder instance.
     * @param array $filters Array of filters from the request.
     * @param array $allowedColumns List of allowed regular columns (directly on the table).
     * @return Builder
     */
    public static function applyFilters(Builder $query, array $filters, array $allowedColumns = []): Builder
    {
        foreach ($filters as $field => $criteria) {
            // If the field is a regular column on the model
            if (in_array($field, $allowedColumns)) {
                if (is_array($criteria)) {
                    // Example: filters[name][like]=%ProjectA%
                    foreach ($criteria as $operator => $value) {
                        switch (strtolower($operator)) {
                            case 'like':
                                $query->where($field, 'LIKE', $value);
                                break;
                            case 'gt':
                                $query->where($field, '>', $value);
                                break;
                            case 'lt':
                                $query->where($field, '<', $value);
                                break;
                            default:
                                $query->where($field, '=', $value);
                                break;
                        }
                    }
                } else {
                    // Direct equality
                    $query->where($field, '=', $criteria);
                }
            } else {
                // Otherwise, assume it's an EAV attribute.
                // This requires that your model has a relationship named "attributeValues"
                // and that each attributeValue has an "attribute" relationship.
                $query->whereHas('attributeValues', function ($q) use ($field, $criteria) {
                    // Ensure we are filtering on the correct attribute name.
                    $q->whereHas('attribute', function ($q2) use ($field) {
                        $q2->where('name', $field);
                    });

                    // Apply the criteria on the value.
                    if (is_array($criteria)) {
                        foreach ($criteria as $operator => $value) {
                            switch (strtolower($operator)) {
                                case 'like':
                                    $q->where('value', 'LIKE', $value);
                                    break;
                                case 'gt':
                                    $q->where('value', '>', $value);
                                    break;
                                case 'lt':
                                    $q->where('value', '<', $value);
                                    break;
                                default:
                                    $q->where('value', '=', $value);
                                    break;
                            }
                        }
                    } else {
                        $q->where('value', '=', $criteria);
                    }
                });
            }
        }

        return $query;
    }
}
