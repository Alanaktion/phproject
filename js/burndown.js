function oSize(obj) {
	var size = 0,
		key;
	for (key in obj) {
		if (obj.hasOwnProperty(key)) size++;
	}
	return size;
}

Burndown = {
	chart: {},
	initialized: false,
	data: {
		labels: [],
		datasets: []
	},
	targetVelocity: {
		fillColor: "rgba(105,9,255,0.1)",
		strokeColor: "rgba(105,9,255,.5)",
		pointColor: "rgba(105,9,255,0)",
		pointStrokeColor: "rgba(105,9,255,0)",
		data: []
	},
	actualVelocity: {
		fillColor: "rgba(255,132,0,0.0)",
		strokeColor: "rgba(255,132,0,1)",
		pointColor: "rgba(220,220,220,1)",
		pointStrokeColor: "#B96000",
		data: []
	},
	hoursDay: {
		fillColor: "rgba(60,185,145,0.2)",
		strokeColor: "rgba(60,185,145,1)",
		pointColor: "rgba(220,220,220,1)",
		pointStrokeColor: "rgba(60,185,145,1)",
		data: []
	},
	days: 0,
	initialHours: 0,
	canvasId: "burndown",
	init: function(data) {
		if(!data) {
			return false;
		}

		// Apply labels to datasets
		Burndown.actualVelocity.label = BurndownLegendDict.actual_velocity;
		Burndown.targetVelocity.label = BurndownLegendDict.target_velocity;
		Burndown.hoursDay.label = BurndownLegendDict.hours_remaining;

		// Populate datsets
		Burndown.createData(data);
		Burndown.data.datasets.push(Burndown.actualVelocity);
		Burndown.data.datasets.push(Burndown.targetVelocity);
		Burndown.data.datasets.push(Burndown.hoursDay);

		// Generate chart
		var ctx = document.getElementById(Burndown.canvasId).getContext("2d");
		Burndown.chart = new Chart(ctx).Line(Burndown.data, Burndown.options);

		Burndown.initialized = true;

	},
	createData: function(data) {
		if(!data) {
			return false;
		}

		// Set burndown days
		Burndown.days = oSize(data);
		var i = 0;
		sum = 0;

		// Strip down to initial daty and set initial hours
		$.each(data, function(key, val) {
			if (i === 0) {
				Burndown.initialHours = this.remaining;
			} else {
				return;
			}
			i++;
		});

		var target = Burndown.initialHours,
			ratio = Burndown.initialHours / (Burndown.days - 1);
		i = Burndown.days; // Tick days down for daily hours needed

		$.each(data, function(key, val) {
			// Set labels for each day
			Burndown.data.labels.push(key);

			// Set actual velocity
			if (val) { // Check if it's null for actual velocity
				Burndown.actualVelocity.data.push(this.remaining);
				Burndown.hoursDay.data.push(this.remaining / i);
			}

			// Set target data
			Burndown.targetVelocity.data.push(target);
			target = target - ratio;

			i--;

		});
	},
	options: {
		scaleBeginAtZero: true,
		bezierCurve: false,
		pointDot: true,
		pointDotRadius: 2,
		pointDotStrokeWidth: 1,
		datasetStroke: true,
		datasetStrokeWidth: 2,
		datasetFill: true,
		animation: false,
		responsive: true,
	}
};
