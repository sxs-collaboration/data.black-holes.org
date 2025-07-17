//
// We want to add tooltips to every column header, if applicable
//
function tooltipHeader() {
    var tooltip = this.tooltip;
    if (typeof tooltip !== "undefined") {
        return $("<div>").prop("title", tooltip).text(this.title);
    } else {
        return $("<div>").text(this.title);
    }
};

// Add the tooltip to any text fields
jsGrid.fields.text.prototype.headerTemplate = tooltipHeader;


//
// For some reason, jsGrid doesn't come with a decent float field type, so we provide it here.
//
function FloatNumberField(config) {
    jsGrid.NumberField.call(this, config);
};
FloatNumberField.prototype = new jsGrid.NumberField({
    filterTemplate: function() {
        var a = this._grid;
        this._lower_label = $("<label>").attr("for", this.name+"_"+this.type+"_lower").text("From:")
            .css("display", "block").css("text-align", "left").css("font-size", "50%");
        this._lower = $("<input>").attr("type", "number").attr("id", this.name+"_"+this.type+"_lower")
            .keypress(function(b){13===b.which&&(a.search(),b.preventDefault())});
        this._upper_label = $("<label>").attr("for", this.name+"_"+this.type+"_upper").text("To:")
            .css("display", "block").css("text-align", "left").css("font-size", "50%");
        this._upper = $("<input>").attr("type", "number").attr("id", this.name+"_"+this.type+"_upper")
            .keypress(function(b){13===b.which&&(a.search(),b.preventDefault())});
        return $("<div>").append(this._lower_label).append(this._lower)
            .append('<br>')
            .append(this._upper_label).append(this._upper);
    },
    filterValue: function() {
        if ((this._lower.val() === undefined || isNaN(this._lower.val()))
            && (this._upper.val() === undefined || isNaN(this._upper.val()))) {
            return undefined;
        }
        return {
            lower: parseFloat(this._lower.val()),
            upper: parseFloat(this._upper.val())
        }
    },
    headerTemplate: tooltipHeader,
    itemTemplate: function(value, item) {
        var prefix = "";
        if (typeof value === "string" || value instanceof String) {
            if (value.charAt(0) == "<") {
                prefix = "<";
                value = parseFloat(value.substr(1));
            } else {
                return "&mdash;";
            }
        }
        if (typeof value === "undefined") {
            return "&mdash;";
        } else if (value == 0 || (Math.abs(value) > 1e-1 && Math.abs(value) <= 1e5)) {
            return prefix + value.toPrecision(4);
        } else {
            try {
                var mantissa_and_exponent = value.toExponential().toString().split("e");
            } catch(err) {
                console.log(err.message, value);
                return "&mdash;";
            }
            return prefix + Number(mantissa_and_exponent[0]).toPrecision(4) + "&times;10<sup>" + mantissa_and_exponent[1] + "</sup>";
        }
    },
});
jsGrid.fields.float = FloatNumberField;


jsGrid.sortStrategies.limit_or_float = function(value1, value2) {
    // Treat "[unknown]" as infinite; treat "<x" as "x".
    if (typeof value1 === "string" || value1 instanceof String) {
        if (value1.charAt(0) === "<") {
            value1 = parseFloat(value1.substr(1));
        } else {
            if (typeof value2 === "string" || value2 instanceof String) {
                if (value2.charAt(0) === "<") {
                    return 1;
                } else {
                    return 0;
                }
            } else {
                return 1;
            }
        }
    }
    if (typeof value2 === "string" || value2 instanceof String) {
        if (value2.charAt(0) === "<") {
            value2 = parseFloat(value2.substr(1));
        } else {
            return -1;
        }
    }
    if(value1 < value2) return -1; // return negative value when first is less than second
    if(value1 === value2) return 0; // return zero if values are equal
    if(value1 > value2) return 1; // return positive value when first is greater than second
};


// jsGrid also doesn't come with a way to filter a range of floats.  We need that, so we provide
// this function, which handles strings and ranges of numbers.
var filter_strings_ints_floatranges = function(filter) {
    return function(run) {
        for (var property in filter) {
            if (filter.hasOwnProperty(property) && filter[property] !== undefined) {
                if (typeof filter[property] === "string" || filter[property] instanceof String) {
                    var p = property.replace('_float', '').replace('_text', '');
                    if (filter[property] && run[p] !== undefined) {
                        if ($.isArray(run[p])) {
                            for (var run_prop in run[p]) {
                                if (run_prop.match(filter[property])) {
                                    return true;
                                }
                            }
                            return false;
                        } else {
                            if (! run[p].match(filter[property])) {
                                return false;
                            }
                        }
                    }
                } else {
                    if (filter[property] !== undefined) {
                        if (filter[property].hasOwnProperty('upper') && filter[property].hasOwnProperty('lower')
                            && (filter[property].upper !== undefined || filter[property].lower !== undefined)
                            && (!isNaN(filter[property].upper) || !isNaN(filter[property].lower))) {
                            if (property.endsWith('_vectorx')) {
                                var p = property.replace('_vectorx', '');
                                var q = run[p][0];
                                if (filter[property].upper < q || filter[property].lower > q) {
                                    return false;
                                }
                            } else if (property.endsWith('_vectory')) {
                                var p = property.replace('_vectory', '');
                                var q = run[p][1];
                                if (filter[property].upper < q || filter[property].lower > q) {
                                    return false;
                                }
                            } else if (property.endsWith('_vectorz')) {
                                var p = property.replace('_vectorz', '');
                                var q = run[p][2];
                                if (filter[property].upper < q || filter[property].lower > q) {
                                    return false;
                                }
                            } else if (property.endsWith('_vectormag')) {
                                var p = property.replace('_vectormag', '');
                                var q = Math.sqrt(run[p][0]*run[p][0]+run[p][1]*run[p][1]+run[p][2]*run[p][2]);
                                if (filter[property].upper < q || filter[property].lower > q) {
                                    return false;
                                }
                            } else {
                                var p = property.replace('_float', '').replace('_text', '');
                                if (filter[property].upper < run[p] || filter[property].lower > run[p]) {
                                    return false;
                                }
                            }
                        } else if (!isNaN(filter[property])) {
                            var p = property.replace('_float', '').replace('_text', '');
                            if (filter[property] != run[p]) {
                                return false;
                            }
                        }
                    }
                }
            }
        }
        return true;
    };
};


//
// Add some vector-related fields
//
function VectorX(config) {
    FloatNumberField.call(this, config);
};
VectorX.prototype = new FloatNumberField({
    index: 0,
    readOnly: true,
    sorter: function(value1, value2) {
        return jsGrid.sortStrategies.limit_or_float(value1[this.index], value2[this.index]);
    },
    itemTemplate: function(value, item) {
        if (value === undefined) { return ''; }
        return jsGrid.fields.float.prototype.itemTemplate(value[this.index]);
    },
});
jsGrid.fields.vectorx = VectorX;

function VectorY(config) {
    FloatNumberField.call(this, config);
};
VectorY.prototype = new FloatNumberField({
    index: 1,
    readOnly: true,
    sorter: function(value1, value2) {
        return jsGrid.sortStrategies.limit_or_float(value1[this.index], value2[this.index]);
    },
    itemTemplate: function(value, item) {
        if (value === undefined) { return ''; }
        return jsGrid.fields.float.prototype.itemTemplate(value[this.index]);
    },
});
jsGrid.fields.vectory = VectorY;

function VectorZ(config) {
    FloatNumberField.call(this, config);
};
VectorZ.prototype = new FloatNumberField({
    index: 2,
    readOnly: true,
    sorter: function(value1, value2) {
        return jsGrid.sortStrategies.limit_or_float(value1[this.index], value2[this.index]);
    },
    itemTemplate: function(value, item) {
        if (value === undefined) { return ''; }
        return jsGrid.fields.float.prototype.itemTemplate(value[this.index]);
    },
});
jsGrid.fields.vectorz = VectorZ;

function VectorMag(config) {
    FloatNumberField.call(this, config);
};
VectorMag.prototype = new FloatNumberField({
    readOnly: true,
    sorter: function(value1, value2) {
        // console.log('VectorMag.sorter');
        return jsGrid.sortStrategies.limit_or_float(
            value1[0]*value1[0]+value1[1]*value1[1]+value1[2]*value1[2],
            value2[0]*value2[0]+value2[1]*value2[1]+value2[2]*value2[2]
        );
    },
    itemTemplate: function(value, item) {
        // console.log('VectorMag.itemTemplate');
        if (value === undefined) { return ''; }
        return jsGrid.fields.float.prototype.itemTemplate(
            Math.sqrt(value[0]*value[0]+value[1]*value[1]+value[2]*value[2])
        );
    },
});
jsGrid.fields.vectormag = VectorMag;


//
// These formatters are used to render prettier items in certain columns
//
var format_email = function(value, item) {
    var img = $('<img>', {
        src: "/images/icons/envelope-o.svg",
        width: "20px",
        height: "20px",
        alt: value,
    }).css("border-width", "0px");
    var a = $('<a>',{
        href: "mailto:questions@black-holes.org?cc="+value+"&subject="+item.name,
    });
    return a.append(img);
};
var format_downloads = function(value, item) {
    var span = $('<span>');
    var img = $('<img>', {
        src: "/images/icons/folder-o.svg",
        width: "20px",
        height: "20px",
        alt: value,
    }).css("border-width", "0px");
    var a = $('<a>',{
        // href: 'data/' + item.name,
        href: item.url,
        target: '_blank',
    });
    span.append(a.append(img));
    return span;
};
var sxs_formatters = {
    'sxs_format_email': format_email,
    'sxs_format_downloads': format_downloads,
};
var replace_formatters = function(fields) {
    $.each(fields, function(indexInArray, field) {
        if ("itemTemplate" in field && field.itemTemplate in sxs_formatters) {
            field.itemTemplate = sxs_formatters[field.itemTemplate];
        }
    });
};


//
// These functions are used to select the fields that are displayed in the table at any given time. 
//
var column_selectors_init = function(columns) {
    var column_selectors_div = $("#column_selectors");
    column_selectors_div.empty();
    $.each(columns, function(indexInArray, value) {
        var name = value.name;
        var title = value.title;
        var tooltip = value.tooltip;
        var visible = value.visible;
        var type = value.type;
        if (title === undefined) { title = name; }
        if (visible === undefined) { visible = false; }
        var checkbox = $("<div>").append(
            $("<input>", {
                type: "checkbox",
                name: name + "_" + type,
                id: "column_selector_" + name + "_" + type,
                checked: value.visible
            })
        ).append(
            $("<label>", {
                for: "column_selector_" + name + "_" + type,
                style: "font-family: Times",
                title: tooltip,
                html: title,
            })
        );
        column_selectors_div.append(checkbox);
    });
};
var column_selectors_read = function(grid) {
    var inputs = $("#column_selectors :input");
    var d = {};
    $.each(inputs, function(indexInArray, value) {
        // console.log('input:', value);
        d[value.name] = value.checked;
    });
    var fields = $("#grid").jsGrid("option", "fields");
    $.each(fields, function(indexInArray, value) {
        // console.log('field:', value);
        var name = value.name + "_" + value.type;
        if(name !== undefined) {
            value.visible = d[name];
        }
    });
    $("#grid").jsGrid("render");
    $("#grid").jsGrid("sort", "name", "asc");
};
