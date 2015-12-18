<script>

    jQuery(function($){
        {$langcode = {lang attr="code"}|substr:0:2}

        var jQplotDate = new Date();

        $('#date-picker').val(jQplotDate.getMonth()+1+"/"+jQplotDate.getFullYear());

        $('#date-picker').datetimepicker( {
            locale: "{$langcode}",
            viewMode: 'months',
            format: 'MM/YYYY'
        }).on('dp.change',function (e) {
            var month = e.date.format("M");
            var year = e.date.format("YYYY");

            jQplotDate.setMonth(parseInt(month));

            statInputActive(true);

            {literal}

            retrieveJQPlotJson(
                    month,
                    year,
                    function(){statInputActive(false);},
                    0
            );

            {/literal}
        });

        $("#span-calendar").click(function(e){
            $('#date-picker').focus();
        });

        function statInputActive(type) {
            $('#date-picker').attr('readonly', type);
            $('.js-stats-refresh').attr('disabled', type)
        }

        $(".feed-list").load("{admin_viewurl view='ajax/thelia_news_feed'}");

        jQplotDate.setDate(1); // Set day to 1 so we can add month without 30/31 days of month troubles.

        //jQplotDate.setMonth(parseInt(jQplotDate.getMonth())+1); // Init to actual month

        var url = "{url path='/admin/home/stats'}";
        var jQplotData; // json data
        var jQPlotInstance; // global instance

        var jQPlotsOptions = {
            animate: true,
            axesDefaults: {
                tickOptions: { showMark: true, showGridline: true }
            },
            axes: {
                xaxis: {
                    borderColor: '#ccc',
                    ticks : [],
                    tickOptions:    { showGridline: false }
                },
                yaxis: {
                    tickOptions: { showGridline: true, showMark: false, showLabel: false, shadow: false }
                }
            },
            seriesDefaults: {
                lineWidth: 3,
                shadow : false,
                markerOptions: { shadow : false, style: 'filledCircle', size: 12 }
            },
            grid: {
                background: '#FFF',
                shadow : false,
                borderColor : '#FFF'
            },
            highlighter: {
                show: true,
                sizeAdjust: 7,
                tooltipLocation: 'n',
                tooltipContentEditor: function(str, seriesIndex, pointIndex, plot){

                    // Return axis value : data value
                    //return jQPlotsOptions.axes.xaxis.ticks[pointIndex][1] + ': ' + plot.data[seriesIndex][pointIndex][1];
                    return plot.data[seriesIndex][pointIndex][1];
                }
            }
        };

        {literal}

        // Get initial data Json
        // (jQplotDate.getMonth()+1) because JavaScript counts months from 0 to 11. January is 0. December is 11.
        retrieveJQPlotJson((jQplotDate.getMonth()+1), jQplotDate.getFullYear());

        $('[data-toggle="jqplot"]').click(function(){

            $(this).toggleClass('active');
            jsonSuccessLoad();

        });

        $('.js-stats-refresh').click(function(e){
            statInputActive(true);

            jQplotDate.setMonth(parseInt(jQplotDate.getMonth()));

            retrieveJQPlotJson(
                    jQplotDate.getMonth(),
                    jQplotDate.getFullYear(),
                    function(){statInputActive(false);},
                    1
            );
        });

        function retrieveJQPlotJson(month, year, callback, flush) {

            if (typeof flush === "undefined") {
                flush = 0;
            }

            $.getJSON(url, {month: month, year: year, flush: flush})
                    .done(function(data) {
                        jQplotData = data;
                        jsonSuccessLoad();
                        if(callback) {
                            callback();
                        }
                    })
                    .fail(jsonFailLoad);
        }

        function initJqplotData(json) {
            var series = [];
            var seriesColors = [];
            $('[data-toggle="jqplot"].active').each(function(i){
                var position = $(this).index();
                series.push(json.series[position].data);
                seriesColors.push(json.series[position].color);
            });

            // Number of days to display ( = data.length in one serie)
            var days = json.series[0].data.length;

            // Add days to xaxis
            var ticks = [];
            for(var i = 1; i < (days+1); i++){
                ticks.push([i-1, i]);
            }
            jQPlotsOptions.axes.xaxis.ticks = ticks;

            // Graph title
            jQPlotsOptions.title = json.title;

            // Graph series colors
            jQPlotsOptions.seriesColors = seriesColors;

            return series;
        }

        function jsonFailLoad(data) {
            $('#jqplot').html('<div class="alert alert-danger">An error occurred while reading from JSON file</div>');
        }

        function jsonSuccessLoad() {

            // Init jQPlot
            var series = initJqplotData(jQplotData);

            // Start jQPlot
            if(jQPlotInstance) {
                jQPlotInstance.destroy();
            }
            jQPlotInstance = $.jqplot("jqplot", series, jQPlotsOptions);

            $(window).bind('resize', function(event, ui) {
                jQPlotInstance.replot( { resetAxes: true } );
            });

        }
        {/literal}


    });

</script>