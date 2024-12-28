<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Nunito', sans-serif;
        }
        .clickable-box {
            cursor: pointer;
        }
    </style>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="antialiased">
    <div class="relative flex items-top justify-center min-h-screen bg-gray-100 dark:bg-gray-900 sm:items-center py-4 sm:pt-0">
        <div class="flex justify-center mb-4">
            <label for="category" class="mr-2 text-gray-700 dark:text-gray-300">Choose Category:</label>
            <select id="category" class="bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-white" onchange="updateCategory()">
                <option value="all" {{ $selectedCategory == 'all' ? 'selected' : '' }}>All</option>
                <option value="Dessert" {{ $selectedCategory == 'Dessert' ? 'selected' : '' }}>Dessert</option>
                <option value="Meal" {{ $selectedCategory == 'Meal' ? 'selected' : '' }}>Meal</option>
            </select>
        </div>

        <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow sm:rounded-lg grid grid-cols-1 sm:grid-cols-2 gap-6 mx-4">
            <form id="form1" action="{{ route('choose-meal') }}" method="POST" class="clickable-box p-6 hover:bg-gray-200 dark:hover:bg-gray-700 transition duration-300" onclick="document.getElementById('form1').submit();">
                @csrf
                <input type="hidden" name="chosen_meal" value="{{ $meal2['strMeal'] }}">
                <input type="hidden" name="category" value="{{ $selectedCategory }}">
                <div class="flex items-center">
                    <div class="ml-4 text-lg leading-7 font-semibold text-gray-900 dark:text-white">{{ $meal1['strMeal'] }}</div>
                </div>
                <img src="{{ $meal1['strMealThumb'] }}" class="h-48 w-48 mx-auto"/>
                <div class="text-center">
                    <div class="mt-2 text-gray-600 dark:text-gray-400 text-sm">Category: {{ $meal1['strCategory'] }}</div>
                    <div class="mt-2 text-gray-600 dark:text-gray-400 text-sm">Area: {{ $meal1['strArea'] }}</div>
                </div>
            </form>

            <form id="form2" action="{{ route('choose-meal') }}" method="POST" class="clickable-box p-6 hover:bg-gray-200 dark:hover:bg-gray-700 transition duration-300" onclick="document.getElementById('form2').submit();">
                @csrf
                <input type="hidden" name="chosen_meal" value="{{ $meal1['strMeal'] }}">
                <input type="hidden" name="category" value="{{ $selectedCategory }}">
                <div class="flex items-center">
                    <div class="ml-4 text-lg leading-7 font-semibold text-gray-900 dark:text-white">{{ $meal2['strMeal'] }}</div>
                </div>
                <img src="{{ $meal2['strMealThumb'] }}" class="h-48 w-48 mx-auto"/>
                <div class="text-center">
                    <div class="mt-2 text-gray-600 dark:text-gray-400 text-sm">Category: {{ $meal2['strCategory'] }}</div>
                    <div class="mt-2 text-gray-600 dark:text-gray-400 text-sm">Area: {{ $meal2['strArea'] }}</div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateCategory() {
            const category = document.getElementById('category').value;
            window.location.href = `/meal?category=${category}`;
        }
    </script>
</body>
</html>
