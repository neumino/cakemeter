$(document).ready(function() {

    var margin = {top: 20, right: 20, bottom: 30, left: 50};
    var width = 960 - margin.left - margin.right;
    var height = 500 - margin.top - margin.bottom;

    var x = d3.time.scale()
        .range([0, width]);

    var y = d3.scale.linear()
        .range([height, 0]);

    var xAxis = d3.svg.axis()
        .scale(x)
        .orient("bottom");

    var yAxis = d3.svg.axis()
        .scale(y)
        .orient("left");

    var svg = d3.select("body").append("svg")
        .attr("width", width + margin.left + margin.right)
        .attr("height", height + margin.top + margin.bottom)
        .append("g")
        .attr("transform", "translate(" + margin.left + "," + margin.top + ")");

    d3.json("data.php", function(error, data) {
        var time = [];
        var stars = []

        for(var i in data) {
            stars_data = data[i].stars;
            for(var j in stars_data) {
                time.push(stars_data[j].time);

                if (data[i].repo == "mongo") {
                    stars.push(stars_data[j].stars/2);
                }
                stars.push(0);
            }
        }
        
        x.domain(d3.extent(time, function(d) { return d; }));
        y.domain(d3.extent(stars, function(d) { return d; }));


        for(var i in data) {
            stars_data = data[i].stars;
            var repo = data[i].repo;


            var line = d3.svg.line()
                .x(function(d) { return x(d.time); })
                .y(function(d) { 
                    if (repo == "mongo") {
                        return y(d.stars/2);
                    }
                    return y(d.stars);
                });


            svg.append("g")
                .attr("class", "x axis")
                .attr("transform", "translate(0," + height + ")")
                .call(xAxis);

            svg.append("g")
                .attr("class", "y axis")
                .call(yAxis)
                .append("text")
                .attr("transform", "rotate(-90)")
                .attr("y", 6)
                .attr("dy", ".71em")
                .style("text-anchor", "end")
                .text("Stars");

            svg.append("path")
                .datum(stars_data)
                .attr("class", "line "+repo)
                .attr("d", line);
        }
    });

});
