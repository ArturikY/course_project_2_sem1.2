# 🛣️ Анализ опасности маршрутов (краткая версия)

## 💡 Идея

Пользователь строит маршрут через Yandex.Maps. Система получает 3 варианта маршрута, проверяет их пересечение с опасными зонами и показывает сравнение с рекомендацией.

---

## 📍 Получение координат маршрута

### Вариант 1: Через routePanelControl

```javascript
const routePanel = map.controls.get('routePanelControl');
routePanel.routePanel.getRouteAsync().then(function(route) {
    const geometry = route.getGeometry();
    const coordinates = geometry.getCoordinates(); // [[lat, lon], ...]
    analyzeRouteSafety(coordinates);
});
```

### Вариант 2: Через MultiRoute

```javascript
routeMultiRoute.events.add('update', function() {
    const routes = routeMultiRoute.getRoutes();
    routes.each(function(route, index) {
        const coordinates = route.getGeometry().getCoordinates();
        analyzeRouteSafety(coordinates, index);
    });
});
```

---

## 🔍 Проверка пересечения с опасными зонами

### Формула расстояния (Haversine)

```javascript
function distance(lat1, lon1, lat2, lon2) {
    const R = 6371000; // Радиус Земли в метрах
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}
```

### Проверка пересечения

```javascript
function routePassesThroughZone(routeCoordinates, zone) {
    const [zoneLat, zoneLon] = zone.center;
    const zoneRadius = zone.radius;
    
    for (let point of routeCoordinates) {
        const [pointLat, pointLon] = point;
        const dist = distance(pointLat, pointLon, zoneLat, zoneLon);
        if (dist <= zoneRadius) {
            return true;
        }
    }
    return false;
}
```

### Оптимизация (предварительная фильтрация по bbox)

```javascript
function getZonesNearRoute(routeCoordinates, allZones) {
    const routeBbox = getRouteBbox(routeCoordinates);
    return allZones.filter(zone => bboxesIntersect(routeBbox, zone.bbox));
}
```

---

## 📊 Оценка опасности маршрута

### Вариант 1: Подсчет пересечений (простой)

```javascript
function calculateRouteSafety(routeCoordinates, allZones) {
    let lowCount = 0, mediumCount = 0, highCount = 0;
    const zonesPassed = [];
    
    for (let zone of allZones) {
        if (routePassesThroughZone(routeCoordinates, zone)) {
            zonesPassed.push(zone);
            if (zone.risk_level === 'low') lowCount++;
            else if (zone.risk_level === 'medium') mediumCount++;
            else if (zone.risk_level === 'high') highCount++;
        }
    }
    
    const safetyScore = lowCount * 1 + mediumCount * 2 + highCount * 3;
    
    return {
        totalZones: zonesPassed.length,
        lowCount, mediumCount, highCount,
        safetyScore,
        zones: zonesPassed
    };
}
```

### Вариант 2: Длина пути через зоны (точный)

```javascript
function calculateLengthInZone(routeCoordinates, zone) {
    const [zoneLat, zoneLon] = zone.center;
    const zoneRadius = zone.radius;
    let lengthInZone = 0;
    
    for (let i = 0; i < routeCoordinates.length - 1; i++) {
        const [lat1, lon1] = routeCoordinates[i];
        const [lat2, lon2] = routeCoordinates[i + 1];
        const dist1 = distance(lat1, lon1, zoneLat, zoneLon);
        const dist2 = distance(lat2, lon2, zoneLat, zoneLon);
        const segmentLength = distance(lat1, lon1, lat2, lon2);
        
        if (dist1 <= zoneRadius && dist2 <= zoneRadius) {
            lengthInZone += segmentLength;
        } else if (dist1 <= zoneRadius || dist2 <= zoneRadius) {
            lengthInZone += segmentLength / 2;
        }
    }
    return lengthInZone;
}
```

### Вариант 3: Комбинированный (рекомендуемый)

```javascript
function calculateRouteSafetyCombined(routeCoordinates, allZones) {
    const routeLength = calculateRouteLength(routeCoordinates);
    let lowCount = 0, mediumCount = 0, highCount = 0;
    let lowLength = 0, mediumLength = 0, highLength = 0;
    let maxRiskLevel = 'safe';
    const zonesPassed = [];
    
    for (let zone of allZones) {
        const lengthInZone = calculateLengthInZone(routeCoordinates, zone);
        if (lengthInZone > 0) {
            zonesPassed.push({zone, length: lengthInZone});
            if (zone.risk_level === 'low') {
                lowCount++; lowLength += lengthInZone;
            } else if (zone.risk_level === 'medium') {
                mediumCount++; mediumLength += lengthInZone;
                if (maxRiskLevel !== 'high') maxRiskLevel = 'medium';
            } else if (zone.risk_level === 'high') {
                highCount++; highLength += lengthInZone;
                maxRiskLevel = 'high';
            }
        }
    }
    
    const countScore = lowCount * 1 + mediumCount * 2 + highCount * 3;
    const lengthScore = (lowLength * 1 + mediumLength * 2 + highLength * 3) / 1000;
    const safetyScore = countScore * 0.4 + lengthScore * 0.6;
    const dangerousPercentage = ((lowLength + mediumLength + highLength) / routeLength) * 100;
    
    return {
        totalZones: zonesPassed.length,
        lowCount, mediumCount, highCount,
        lowLength, mediumLength, highLength,
        maxRiskLevel, safetyScore, dangerousPercentage,
        zones: zonesPassed
    };
}
```

---

## 🎨 Отображение результатов

### Информация для каждого маршрута:

```
Маршрут 1:
  ✅ Опасных зон: 0
  📏 Длина: 12.5 км
  Оценка: Безопасный

Маршрут 2:
  ⚠️ Опасных зон: 3 (2 средних, 1 высокий)
  📏 Длина: 11.8 км
  🔴 Опасный участок: 2.3 км (19%)
  Оценка: Умеренно опасный

Маршрут 3:
  🔴 Опасных зон: 5 (1 низкий, 3 средних, 1 высокий)
  📏 Длина: 10.2 км
  🔴 Опасный участок: 4.1 км (40%)
  Оценка: Опасный
```

### Визуализация на карте:

- Подсветить участки маршрута, проходящие через опасные зоны
- Показать зоны, через которые проходит маршрут
- Разные цвета для разных уровней опасности

### Рекомендация:

- Автоматически выбирать маршрут с минимальным `safetyScore`
- Показывать: "Рекомендуем Маршрут 1 - самый безопасный"

---

## 🔧 Технические детали

### Структура данных опасных зон:

```javascript
const zone = {
    geometry: {
        coordinates: [lon, lat] // Центр зоны
    },
    properties: {
        radius: 500, // Радиус в метрах
        risk_level: 'medium', // 'low', 'medium', 'high'
        count: 15,
        density_per_1000m2: 0.25
    }
};
```

### Оптимизация:

1. **Предварительная фильтрация** - проверять только зоны в bbox маршрута
2. **Упрощение геометрии** - проверять не все точки, а каждую N-ю
3. **Кэширование** - кэшировать вычисленные расстояния

---

## 📋 План реализации

1. **Получение маршрутов** - подключиться к событиям routePanelControl/MultiRoute
2. **Проверка пересечений** - реализовать distance() и routePassesThroughZone()
3. **Оценка опасности** - реализовать calculateRouteSafetyCombined()
4. **Визуализация** - создать UI для отображения результатов
5. **Рекомендации** - показать лучший маршрут пользователю

---

## 🎯 Пример использования

```javascript
function analyzeAllRoutes() {
    const routes = getRoutesFromYandex(); // 3 маршрута
    const allZones = hotspotsData; // Все опасные зоны
    
    const results = routes.map((route, index) => {
        const coordinates = route.getCoordinates();
        const safety = calculateRouteSafetyCombined(coordinates, allZones);
        return {
            routeIndex: index + 1,
            routeLength: calculateRouteLength(coordinates),
            safety: safety
        };
    });
    
    results.sort((a, b) => a.safety.safetyScore - b.safety.safetyScore);
    displayRouteComparison(results);
}
```

---

## 📊 Метрики оценки

- **Количество зон** - сколько опасных зон пересекает маршрут
- **Балл опасности** - взвешенная сумма (low=1, medium=2, high=3)
- **Длина опасного участка** - метры/километры через опасные зоны
- **Процент опасного пути** - доля опасного участка от общей длины
- **Максимальный уровень опасности** - самый опасный уровень на маршруте

---

## ✅ Выводы

1. **Технически реализуемо** - Yandex.Maps API предоставляет координаты маршрутов
2. **Алгоритм понятен** - проверка расстояния от точек маршрута до центров зон
3. **Оценка опасности** - комбинированный подход (количество + длина)
4. **Визуализация** - подсветка опасных участков на карте
5. **Полезно для пользователя** - помогает выбрать безопасный маршрут

**Статус:** Концепция, готово к реализации  
**Сложность:** ⭐⭐⭐ (средняя)




