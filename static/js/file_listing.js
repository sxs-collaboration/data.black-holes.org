function byte_size_to_human(bytes) {
    var units = ["B", "KiB","MiB","GiB","TiB","PiB","EiB","ZiB","YiB"];
    var index = 0;
    if (bytes == 0) {
        return "0 B";
    } else {
        while(Math.abs(bytes) >= 1024 && index < units.length - 1) {
            bytes /= 1024;
            index++;
        }
        return bytes.toFixed(1) + " " + units[index];
    }
}


// var css_special_characters = "!\"#$%&'()*+-,./:;<=>?@[\\]^`{|}~";
// var css_special_regex = new Regex();
// function escape_selector_string(selector) {
//     console.log(special_characters);
// }


function file_tree_ul(tree) {
    var array = [];
    $.each(tree, function(indexInArray, value) {
        var li = $("<li>", {
            "html": value.basename,
        });
        if (value.type == "directory") {
            li.addClass("directory");
            var ul = $("<ul>").append(file_tree(value.children));
            li.append(ul);
        } else {
            li.addClass("file");
        }
        array.push(li);
    });
    return array;
};

var type_to_css_and_description = {
    ".h5": ["hdf5", "HDF5"],
    ".hdf5": ["hdf5", "HDF5"],
    ".txt": ["txt", "Text"],
    ".pl": ["txt", "Perl"],
    ".perl": ["txt", "Perl"],
    ".out": ["txt", "Output"],
    ".tgz": ["tgz", "Archive"],
    ".zip": ["tgz", "Archive"],
};

function map_type_to_css_and_description(type) {
    if (type_to_css_and_description[type] !== undefined) {
        return type_to_css_and_description[type];
    } else {
        return ["", ""];
    }
};

function file_tree_table(tree, root, directory) {
    $.each(tree, function(indexInArray, value) {
        var tr = $("<tr>");
        if (value.type == "directory") {
            tr.addClass("directory");
            tr.attr("data-path", value.path);
            // Checkbox (empty for directories)
            tr.append($("<td>"));
            // Item name
            tr.append($("<td>", {
                "class": "file_listing directory",
                "html": value.basename
            }));
            // Item type
            tr.append($("<td>", {"html": "Directory"}));
            // Item size (empty for directories)
            tr.append($("<td>"));
            // Allow clicks anywhere on a directory line to show files
            tr.click(function() {
                var directory = $(this).attr("data-path");
                $(this).find(".file_listing.directory").toggleClass("open");
                $("tr[data-directory='" + directory + "']").toggle();
            });
            // Now attach this row to the tree
            root.append(tr);
            // Append all those children
            file_tree_table(value.children, root, value.path);
        } else {
            var css_description = map_type_to_css_and_description(value.type.toLowerCase());
            var type_class = css_description[0];
            var description = css_description[1];
            tr.addClass("file");
            // Checkbox
            var check = $("<input>", {
                "name": "path",
                "value": value.path,
                "type": "checkbox",
                "data-filesize": value.size
            });
            check.click(function (e) {
                var sign = (this.checked ? +1 : -1);
                var filesize = parseInt(this.dataset.filesize);
                console.log('input data-filesize =', filesize, window.total_download_size);
                window.total_downloads += sign;
                window.total_download_size += sign*filesize;
                console.log('\t\t', window.total_download_size, sign, this.checked);
                if(window.total_downloads > 1
                   && window.total_download_size > 1000000000) {
                    sign = -1;
                    alert('Total file size ' + window.total_download_size + ' too big for group download!\n'
                          + 'Select just one file at a time.');
                    window.total_downloads += sign;
                    window.total_download_size += sign*filesize;
                    return false;
                }
                e.stopPropagation();
            });
            tr.append($("<td>", {
                "class": "file_listing_checkbox",
            }).append(check));
            // Item name
            if (value.depth > 0) {
                var class_names = "file_listing " + type_class + " indent" + value.depth;
            } else {
                var class_names = "file_listing " + type_class;
            }
            tr.append($("<td>", {
                "class": class_names,
                "html": value.basename,
            }));
            // Item type
            tr.append($("<td>", {
                "html": description,
            }));
            // Item size
            tr.append($("<td>", {
                "html": byte_size_to_human(value.size),
            }));
            // Allow clicks anywhere on a file line to click the checkbox (on or off)
            tr.click(function() { $(this).find(":checkbox").click(); });
            // Append and hide this element if it's in a directory
            if (directory !== undefined) {
                tr.attr("data-directory", directory);
                root.append(tr.hide());
            } else {
                root.append(tr);
            }
        }
    });
};
