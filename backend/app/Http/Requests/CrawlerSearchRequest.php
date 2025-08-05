<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrawlerSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $searchId = $this->route('search')?->id;
        
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('crawler_searches', 'name')
                    ->where('user_id', $this->user()->id)
                    ->ignore($searchId)
            ],
            'query' => 'required|string|max:255|min:2',
            'category_id' => 'nullable|exists:categories,id',
            'price_min' => 'nullable|integer|min:0|max:999999',
            'price_max' => 'nullable|integer|min:0|max:999999|gte:price_min',
            'max_age_days' => 'nullable|integer|min:1|max:365',
            'location' => 'nullable|string|max:255',
            'radius_km' => 'nullable|integer|min:1|max:500',
            'notification_enabled' => 'boolean',
            'crawl_interval_hours' => 'nullable|integer|min:1|max:168', // Max 1 week
            'settings' => 'nullable|array',
            'settings.exclude_keywords' => 'nullable|array',
            'settings.exclude_keywords.*' => 'string|max:100',
            'settings.include_keywords' => 'nullable|array',
            'settings.include_keywords.*' => 'string|max:100',
            'settings.min_images' => 'nullable|integer|min:0|max:50',
            'settings.exclude_sellers' => 'nullable|array',
            'settings.exclude_sellers.*' => 'string|max:255',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Názov vyhľadávania je povinný.',
            'name.unique' => 'Vyhľadávanie s týmto názvom už existuje.',
            'query.required' => 'Vyhľadávací dotaz je povinný.',
            'query.min' => 'Vyhľadávací dotaz musí mať aspoň 2 znaky.',
            'price_min.integer' => 'Minimálna cena musí byť číslo.',
            'price_max.integer' => 'Maximálna cena musí byť číslo.',
            'price_max.gte' => 'Maximálna cena musí byť vyššia alebo rovná minimálnej cene.',
            'category_id.exists' => 'Vybratá kategória neexistuje.',
            'max_age_days.min' => 'Maximálny vek inzerátov musí byť aspoň 1 deň.',
            'max_age_days.max' => 'Maximálny vek inzerátov nemôže byť viac ako 365 dní.',
            'radius_km.min' => 'Polomer vyhľadávania musí byť aspoň 1 km.',
            'radius_km.max' => 'Polomer vyhľadávania nemôže byť viac ako 500 km.',
            'crawl_interval_hours.min' => 'Interval crawlovania musí byť aspoň 1 hodina.',
            'crawl_interval_hours.max' => 'Interval crawlovania nemôže byť viac ako 168 hodín (1 týždeň).',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Clean and prepare query
        if ($this->has('query')) {
            $this->merge([
                'query' => trim($this->query)
            ]);
        }
        
        // Set default values
        $defaults = [
            'max_age_days' => 14,
            'radius_km' => 25,
            'crawl_interval_hours' => 2,
            'notification_enabled' => true,
        ];
        
        foreach ($defaults as $key => $default) {
            if (!$this->has($key) || $this->input($key) === null) {
                $this->merge([$key => $default]);
            }
        }
        
        // Clean settings
        if ($this->has('settings')) {
            $settings = $this->settings ?? [];
            
            // Clean exclude/include keywords
            if (isset($settings['exclude_keywords'])) {
                $settings['exclude_keywords'] = array_filter(
                    array_map('trim', $settings['exclude_keywords']),
                    fn($keyword) => !empty($keyword)
                );
            }
            
            if (isset($settings['include_keywords'])) {
                $settings['include_keywords'] = array_filter(
                    array_map('trim', $settings['include_keywords']),
                    fn($keyword) => !empty($keyword)
                );
            }
            
            if (isset($settings['exclude_sellers'])) {
                $settings['exclude_sellers'] = array_filter(
                    array_map('trim', $settings['exclude_sellers']),
                    fn($seller) => !empty($seller)
                );
            }
            
            $this->merge(['settings' => $settings]);
        }
    }

    /**
     * Get validated data with computed fields
     */
    public function validatedWithComputed(): array
    {
        $validated = $this->validated();
        
        // Generate search name if not provided
        if (empty($validated['name'])) {
            $validated['name'] = $this->generateSearchName($validated['query'], $validated['category_id'] ?? null);
        }
        
        return $validated;
    }

    /**
     * Generate search name from query and category
     */
    private function generateSearchName(string $query, ?int $categoryId): string
    {
        $name = ucfirst($query);
        
        if ($categoryId) {
            $category = \App\Models\Category::find($categoryId);
            if ($category) {
                $name .= ' - ' . $category->name;
            }
        }
        
        // Ensure uniqueness
        $baseName = $name;
        $counter = 1;
        
        while ($this->user()->crawlerSearches()->where('name', $name)->exists()) {
            $name = $baseName . ' (' . $counter . ')';
            $counter++;
        }
        
        return $name;
    }
}