"""
Hacked together from MyBibtexToHtml.php, which was in turn hacked together stealing pieces from 
  1) BibTeX 2 HTML  
     Author: Andreas Classen <www.classen.be> - Version 20100317   
  2) bibtexParse
     Author: Mark Grimshaw http://bibliophile.sourceforge.net

"""

import re


def make_accent_table():
    vowels = ["a", "e", "i", "o", "u", "A", "E", "I", "O", "U"]
    vowel_accents = {
        "\\'{{{0}}}": "&{0}acute;",
        "\\'{{\\{0}}}": "&{0}acute;",
        "\\`{{{0}}}": "&{0}grave;",
        "\\^{{{0}}}": "&{0}circ;",
        "\\\"{{{0}}}": "&{0}uml;",
        "\\'{0}": "&{0}acute;",
        "\\`{0}": "&{0}grave;",
        "\\^{0}": "&{0}circ;",
        "\\\"{0}": "&{0}uml;",
    }
    table = {k.format(v): vowel_accents[k].format(v) for v in vowels for k in vowel_accents}
    b = {
        "\\vr": "&#X0159;",
        "\\v{r}": "&#X0159;",
        "\\vR": "&#X0158;",
        "\\v{R}": "&#X0158;",
        "\\vs": "&#X0161;",
        "\\v{s}": "&#X0161;",
        "\\vS": "&#X0160;",
        "\\v{S}": "&#X0160;",
        "\\vi": "&#X012D;",
        "\\v{i}": "&#X012D;",
        "\\vI": "&#X012C;",
        "\\v{I}": "&#X012C;",
        "\\c{c}": "&ccedil;",
        "\\c{C}": "&Ccedil;",
        "\\~{a}": "&atilde;",
        "\\~{A}": "&Atilde;",
        "\\~{o}": "&otilde;",
        "\\~{O}": "&Otilde;",
        "\\~{n}": "&ntilde;",
        "\\~{N}": "&Ntilde;",
        "{\\u g}": "&#287;",
    }
    table.update(b)
    return table


def strtr(s, repl):
    "https://stackoverflow.com/a/10931977/1194883"
    pattern = '|'.join(map(re.escape, sorted(repl, key=len, reverse=True)))
    return re.sub(pattern, lambda m: repl[m.group()], s)


def replace_accents(s, table):
    "function to replace TeX accents with html accents"
    o = strtr(s, table)
    return o


def closing_delimiter(string, delimiter_end):
    "find the closing delimter taking into account either "BLA" or {BLA}"
    bracelevel = 0
    openquote = False

    for i, c in enumerate(string):
        if (c == '"') and bracelevel != 0:
	    openquote = not openquote
        elif c == '{':
	    bracelevel += 1
        elif c == '}':
	    bracelevel -= 1
        if (c == delimiter_end) and (not openquote) and (not bracelevel):
	    return i

    return 0


def extract_entries(file_content):
    "parses a bibTex file into an array of strings, one for each entry"
    
    inside_entry = False
    possible_entry_start = False
    entry = ""
    number_of_entries = 0

    for i, c in enumerate(file_content):
        line = file_content[i]
        if possible_entry_start:
            line = possible_entry_start + line
    if (!$inside_entry && strchr($line,"@"))
          {
	      // throw all characters before the '@'
	      $line=strstr($line,'@');
	if(!strchr($line, "{") && !strchr($line, "("))
	      $possible_entry_start = $line;
	elseif(preg_match("/@.*([{(])/U", preg_quote($line), $matches))
	  {
	    $inside_entry = TRUE;
	    if ($matches[1] == '{')
	      $delimitEnd = '}';
	    else
	      $delimitEnd = ')';
	    $possible_entry_start = FALSE;
	  }
      }
    if ($inside_entry)
      {
	$entry .= " ".$line;
	if ($j=closingDelimiter($entry,$delimitEnd))
	  {
	    // all characters after the delimiter are thrown but the remaining 
	    // characters must be kept since they may start the next entry !!!
	    $lastLine = substr($entry,$j+1);
	    $entry = substr($entry,0,$j+1);
	    // Strip excess whitespaces from the entry 
	    $entry = preg_replace('/\s\s+/', ' ', $entry);
	    $entries[$numberOfEntries] = $entry;
	    $numberOfEntries++;
	    $entry = strchr($lastLine,"@");
	    if ($entry) 
	      $inside_entry = TRUE;
	    else 
	      $inside_entry = FALSE;
	  }
      }
  }
  return $entries;
} 
