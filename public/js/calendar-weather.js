/**
 * Calendar Weather Integration
 * Adds weather indicators to FullCalendar day cells
 */

(function() {
    'use strict';

    const WEATHER_ICONS = {
        '01d': '☀️', '01n': '🌙',
        '02d': '⛅', '02n': '☁️',
        '03d': '☁️', '03n': '☁️',
        '04d': '☁️', '04n': '☁️',
        '09d': '🌧️', '09n': '🌧️',
        '10d': '🌧️', '10n': '🌧️',
        '11d': '⛈️', '11n': '⛈️',
        '13d': '❄️', '13n': '❄️',
        '50d': '🌫️', '50n': '🌫️',
    };

    function getWeatherIcon(iconCode) {
        return WEATHER_ICONS[iconCode] || '🌤️';
    }

    window.calendarWeatherModule = {
        loadWeatherData: async function(calendarElement) {
            const currentUrl = new URL(window.location.href);
            const city = currentUrl.searchParams.get('city') || '';

            try {
                const response = await fetch(`/calendar/api/weather?city=${encodeURIComponent(city)}`);
                if (!response.ok) {
                    console.log('Weather API returned:', response.status);
                    return;
                }
                
                const weatherData = await response.json();
                console.log('Loaded weather data:', weatherData);
                
                this.applyWeatherToCalendar(calendarElement, weatherData);
            } catch (error) {
                console.log('Weather data not available:', error);
            }
        },

        applyWeatherToCalendar: function(calendarElement, weatherData) {
            if (!weatherData || Object.keys(weatherData).length === 0) {
                console.log('No weather data to apply');
                return;
            }

            // Inject immediately
            this.injectWeatherIndicators(weatherData);

            // Watch for calendar updates
            const observer = new MutationObserver(() => {
                this.injectWeatherIndicators(weatherData);
            });

            observer.observe(calendarElement, {
                childList: true,
                subtree: true,
            });
        },

        injectWeatherIndicators: function(weatherData) {
            document.querySelectorAll('[data-datestr]').forEach((cell) => {
                const dateStr = cell.getAttribute('data-datestr');
                if (!dateStr) return;

                const weather = weatherData[dateStr];
                if (!weather) return;

                // Remove existing indicator
                const existing = cell.querySelector('.weather-indicator');
                if (existing) existing.remove();

                // Create new indicator
                const indicator = document.createElement('div');
                indicator.className = 'weather-indicator';
                indicator.setAttribute('title', weather.description);
                
                const icon = getWeatherIcon(weather.icon);
                const temp = Math.round(weather.tempMax);
                
                indicator.innerHTML = `
                    <span class="weather-icon">${icon}</span>
                    <span class="weather-temp">${temp}°</span>
                `;

                cell.style.position = 'relative';
                cell.appendChild(indicator);
            });
        },
    };
})();

