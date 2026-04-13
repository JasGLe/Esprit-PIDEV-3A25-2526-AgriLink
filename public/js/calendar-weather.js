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
                if (!response.ok) throw new Error('Failed to load weather');
                
                const weatherData = await response.json();
                this.applyWeatherToCalendar(calendarElement, weatherData);
            } catch (error) {
                console.log('Weather data not available:', error);
            }
        },

        applyWeatherToCalendar: function(calendarElement, weatherData) {
            if (!weatherData || Object.keys(weatherData).length === 0) return;

            const observer = new MutationObserver(() => {
                this.injectWeatherIndicators(weatherData);
            });

            observer.observe(calendarElement, {
                childList: true,
                subtree: true,
            });

            this.injectWeatherIndicators(weatherData);
        },

        injectWeatherIndicators: function(weatherData) {
            document.querySelectorAll('[data-datestr]').forEach((cell) => {
                const dateStr = cell.getAttribute('data-datestr');
                if (!dateStr || weatherData[dateStr]) return;

                const existing = cell.querySelector('.weather-indicator');
                if (existing) existing.remove();

                const weather = weatherData[dateStr];
                if (!weather) return;

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
