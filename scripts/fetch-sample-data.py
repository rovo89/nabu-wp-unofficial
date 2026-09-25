#!/usr/bin/env python3
"""Fetch sample content from the Jimdo site of a NABU group.

The point is to fill the test instance with real content of real length:
headings with long German compounds, a realistic amount of text, photos in
their actual aspect ratio, image credits in NABU style. Invented text always
looks too tidy in a layout.

The content belongs to the group and the credited photographers. It is test
material and must not be published; the /service page of the NABU web
builder is explicit about that. The importer marks every entry it creates.

    python3 scripts/fetch-sample-data.py [--source https://…jimdofree.com] [--posts N]

Writes content.json and images/ to scripts/sample-data/.
"""

import argparse
import hashlib
import json
import re
import sys
import urllib.request
from html import escape
from pathlib import Path

from bs4 import BeautifulSoup

try:
    from PIL import Image
except ImportError:  # optional, only flat_share() needs it
    Image = None

BASE = Path(__file__).resolve().parent / "sample-data"
IMAGES = BASE / "images"
UA = "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36"

# NABU puts the photo credit at the end of the caption, e.g. "… (Foto: Name)".
CREDIT = re.compile(r"\s*(?:\(?\s*)?(Foto|Fotos|Grafik|Bild|Quelle)\s*:\s*(.+?)\s*\)?\s*$", re.I | re.S)


def fetch(url, binary=False):
    req = urllib.request.Request(url, headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=30) as r:
        data = r.read()
    return data if binary else data.decode("utf-8", "replace")


def original_image_url(url):
    """Return the URL of the untransformed original image.

    Jimdo encodes the requested size in a `/transf/<spec>/` path segment and
    serves the original for `none`. WordPress generates its own sizes.
    """
    return re.sub(r"/transf/[^/]+/", "/transf/none/", url)


def image_ext(data, url):
    """File extension for downloaded image bytes, going by their signature.

    Unknown bytes fall back to the URL as found on the page: Jimdo names the
    delivered format in the transformation segment (":format=png"), which
    original_image_url() drops.
    """
    if data.startswith(b"\x89PNG\r\n\x1a\n"):
        return ".png"
    if data.startswith(b"\xff\xd8\xff"):
        return ".jpg"
    if data.startswith((b"GIF87a", b"GIF89a")):
        return ".gif"
    if data[:4] == b"RIFF" and data[8:12] == b"WEBP":
        return ".webp"
    return ".png" if ":format=png" in url or url.endswith(".png") else ".jpg"


def download_image(url, hint=""):
    """Download an image once and return its file name, or None on failure."""
    original = original_image_url(url)
    short_hash = hashlib.sha1(original.encode()).hexdigest()[:10]
    # The same image can appear on several pages with different hints. Look it
    # up by the URL hash alone, otherwise it would be stored more than once.
    existing = next(iter(IMAGES.glob(f"*-{short_hash}.*")), None)
    if existing:
        return existing.name

    stem = re.sub(r"[^a-z0-9]+", "-", (hint or "bild").lower()).strip("-")[:40] or "bild"
    try:
        data = fetch(original, binary=True)
        name = f"{stem}-{short_hash}{image_ext(data, url)}"
        (IMAGES / name).write_bytes(data)
    except Exception as e:  # noqa: BLE001
        print(f"    image not downloaded ({e}): {original[:90]}", file=sys.stderr)
        return None
    return name


def flat_share(path):
    """Share of the most frequent colour: a rough "graphic, not photo" score.

    The web builder's campaign banners have large flat areas and burned-in
    text. They make poor hero images because the hero heading would sit on
    top of that text. Photos score much lower.
    """
    if Image is None:
        return 0.0
    try:
        with Image.open(path) as im:
            small = im.convert("RGB").resize((64, 64))
            # 4 bits per channel, so noise doesn't split a flat area into many colours.
            coarse = [(r >> 4, g >> 4, b >> 4) for r, g, b in small.getdata()]
    except Exception:  # noqa: BLE001
        return 0.0

    if not coarse:
        return 0.0

    most_common = max(set(coarse), key=coarse.count)
    return round(coarse.count(most_common) / len(coarse), 3)


def split_credit(text):
    """Split a caption into text and photo credit."""
    text = re.sub(r"\s+", " ", (text or "")).strip()
    if not text:
        return "", ""
    m = CREDIT.search(text)
    if not m:
        return text, ""
    credit = f"{m.group(1).capitalize()}: {m.group(2).strip()}"
    return text[: m.start()].strip(" .,;–—-"), credit


def clean_alt(alt, caption, stem):
    """Return a usable alt text; a photo credit is not one.

    Jimdo sometimes derives a missing alt attribute from the file name
    ("foto-h-may.jpg" becomes "Foto: H. May"), which describes nothing. Fall
    back to the caption, then to the page's stem.
    """
    alt = re.sub(r"\s+", " ", (alt or "")).strip()
    rest, credit = split_credit(alt)
    if credit and not rest:
        alt = ""
    return alt or caption or stem.replace("-", " ")


def clean_inline(el):
    """Unwrap span/font and strip Jimdo's attributes; semantic tags stay."""
    for tag in el.find_all(["span", "font"]):
        tag.unwrap()
    for tag in el.find_all(True):
        for name in ("style", "class", "id", "dir", "data-src", "srcset", "sizes"):
            tag.attrs.pop(name, None)
    return el


def inline_html(el, source_host):
    """Return the cleaned inner HTML of a paragraph."""
    clean_inline(el)
    for a in el.find_all("a"):
        href = a.get("href", "")
        # Links into the source site are dead on the test instance: keep the
        # text, drop the link. Only absolute links elsewhere (nabu.de etc.) stay.
        if source_host in href or not href.startswith("http"):
            a.unwrap()
    html = el.decode_contents()
    html = re.sub(r"<br\s*/?>", " ", html)
    html = re.sub(r"\s+", " ", html).strip()
    return html


def block_paragraph(html):
    """Paragraph block from inline HTML, which is already escaped."""
    return f"<!-- wp:paragraph -->\n<p>{html}</p>\n<!-- /wp:paragraph -->"


def block_heading(text, level=2):
    """Heading block from plain text."""
    return (
        f'<!-- wp:heading {{"level":{level}}} -->\n'
        f'<h{level} class="wp-block-heading">{escape(text, quote=False)}</h{level}>\n'
        f"<!-- /wp:heading -->"
    )


def attr(value):
    """Escape a value for a double-quoted HTML attribute.

    Captions often contain quotes (Plakette "Lebensraum Kirchturm") that
    would otherwise end the alt attribute early.
    """
    return (
        str(value)
        .replace("&", "&amp;")
        .replace('"', "&quot;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
    )


def block_image(file, alt, caption):
    """Return a core/image block with a SAMPLEIMAGE:<file> placeholder src.

    `alt` and `caption` are plain text. The importer finds this exact markup
    with a regex and swaps in the uploaded attachment, so keep its shape.
    """
    caption = escape(caption, quote=False)
    figcaption = f'<figcaption class="wp-element-caption">{caption}</figcaption>' if caption else ""
    return (
        '<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->\n'
        f'<figure class="wp-block-image size-large">'
        f'<img src="SAMPLEIMAGE:{file}" alt="{attr(alt)}"/>{figcaption}</figure>\n'
        "<!-- /wp:image -->"
    )


def module_to_blocks(module, source_host, images, stem):
    """Convert one Jimdo module to block markup.

    Downloaded images are recorded in `images` (file name -> credit and alt).
    """
    classes = module.get("class", [])
    out = []

    def image_from(fig):
        img = fig.find("img")
        if not img:
            return
        src = img.get("data-src") or img.get("src") or ""
        if not src.startswith("http"):
            return
        caption_el = fig.find("figcaption")
        raw_text = caption_el.get_text(" ", strip=True) if caption_el else ""
        caption, credit = split_credit(raw_text)
        file = download_image(src, stem)
        if not file:
            return
        alt = clean_alt(img.get("alt"), caption, stem)
        images[file] = {"credit": credit, "alt": alt}
        out.append(block_image(file, alt, caption))

    if "j-header" in classes:
        el = module.find(["h1", "h2", "h3", "h4"])
        if el:
            level = min(4, max(2, int(el.name[1]) + 1))  # one level down, the title is the h1
            text = re.sub(r"\s+", " ", el.get_text(" ", strip=True))
            if text:
                out.append(block_heading(text, level))

    elif "j-text" in classes:
        for child in module.find_all(["p", "ul", "ol"], recursive=True):
            if child.name in ("ul", "ol"):
                clean_inline(child)
                items = "".join(
                    f"<!-- wp:list-item --><li>{li.decode_contents().strip()}</li><!-- /wp:list-item -->"
                    for li in child.find_all("li")
                )
                if items:
                    tag = child.name
                    attrs = '{"ordered":true}' if tag == "ol" else "{}"
                    out.append(
                        f"<!-- wp:list {attrs} -->\n"
                        f'<{tag} class="wp-block-list">{items}</{tag}>\n<!-- /wp:list -->'
                    )
            else:
                html = inline_html(child, source_host)
                if html and len(html) > 2:
                    out.append(block_paragraph(html))

    elif "j-imageSubtitle" in classes:
        fig = module.find("figure")
        if fig:
            image_from(fig)

    elif "j-textWithImage" in classes:
        fig = module.find("figure")
        if fig:
            image_from(fig)
        for child in module.find_all("p"):
            html = inline_html(child, source_host)
            if html and len(html) > 2:
                out.append(block_paragraph(html))

    return out


def read_page(url, source_host, stem, title_from_h1=False):
    """Split a Jimdo content page into title, subtitle, blocks and images."""
    soup = BeautifulSoup(fetch(url), "lxml")
    area = soup.select_one("#content_area") or soup.select_one("#content_start")
    if not area:
        return None

    # Jimdo nests modules (text columns in a grid, for instance). A plain
    # select() returns parent and child, and the text would appear twice.
    all_modules = area.select("div.j-module")
    modules = [m for m in all_modules if not any(a in all_modules for a in m.parents)]
    images = {}
    title, subtitle = "", ""
    blocks = []

    for i, module in enumerate(modules):
        classes = module.get("class", [])
        if "j-blog-post--tags-wrapper" in classes:  # tag list under blog posts
            continue
        # Blog posts carry their title in the page heading, not in a module,
        # so every j-header in the content is a real subheading.
        if title_from_h1:
            blocks += module_to_blocks(module, source_host, images, stem)
            continue
        # Elsewhere the first j-header is the title, and a second one near the
        # top (within the first three modules) is the subtitle.
        if "j-header" in classes and not title:
            title = re.sub(r"\s+", " ", module.get_text(" ", strip=True))
            continue
        if "j-header" in classes and title and not subtitle and i < 3:
            subtitle = re.sub(r"\s+", " ", module.get_text(" ", strip=True))
            continue
        blocks += module_to_blocks(module, source_host, images, stem)

    # Jimdo occasionally emits the same paragraph twice in a row.
    deduped = []
    for b in blocks:
        if not deduped or b != deduped[-1]:
            deduped.append(b)
    blocks = deduped

    # The first image becomes the featured image. The templates show that
    # above the text anyway; left in the content it would appear twice.
    featured_image = ""
    if blocks and blocks[0].startswith("<!-- wp:image"):
        m = re.search(r"SAMPLEIMAGE:([^\"]+)", blocks[0])
        if m:
            featured_image = m.group(1)
            blocks = blocks[1:]

    return {
        "title": title,
        "subtitle": subtitle,
        "blocks": blocks,
        "images": images,
        "featured_image": featured_image,
    }


def harvest_images(url, stem):
    """Collect all images of a page with their size and credit.

    Used for images not tied to an article: the web builder's 940x300 hero
    banners and the teaser images of its grid elements.
    """
    soup = BeautifulSoup(fetch(url), "lxml")
    # Content area only: the page header carries the source group's logo,
    # which has no place in sample data.
    area = soup.select_one("#content_area") or soup.select_one("#content_start")
    if not area:
        return []

    found = []
    seen = set()

    for img in area.select("img"):
        src = img.get("data-src") or img.get("src") or ""
        if not src.startswith("http") or "image.jimcdn.com" not in src:
            continue
        original = original_image_url(src)
        if original in seen:
            continue
        seen.add(original)

        try:
            width = int(img.get("data-src-width") or 0)
            height = int(img.get("data-src-height") or 0)
        except ValueError:
            width = height = 0
        if width < 400 or height < 200:  # icons, decorative strips
            continue
        if re.search(r"logo|wappen", (img.get("alt") or "") + " " + original, re.I):  # logos, coats of arms
            continue

        figure = img.find_parent("figure")
        caption_el = figure.find("figcaption") if figure else None
        caption, credit = split_credit(caption_el.get_text(" ", strip=True) if caption_el else "")

        file = download_image(src, stem)  # src, not original: it still names the format
        if not file:
            continue

        found.append(
            {
                "file": file,
                "alt": clean_alt(img.get("alt"), caption, stem),
                "caption": caption,
                "credit": credit,
                "width": width,
                "height": height,
                "flat_share": flat_share(IMAGES / file),
            }
        )

    return found


def read_feed(source, source_host, count):
    """Fetch the latest blog posts.

    The feed provides title, link and date; the text comes from the post page.
    The feed's version of the text is shorter and puts the image first, while
    the page keeps the actual reading order.
    """
    rss = fetch(source.rstrip("/") + "/rss/blog")
    entries = re.findall(r"(?s)<item>(.*?)</item>", rss)[:count]
    out = []

    for entry in entries:
        def field(name):
            m = re.search(rf"<{name}>(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?</{name}>", entry, re.S)
            return m.group(1).strip() if m else ""

        title = re.sub(r"\s+", " ", field("title"))
        url = field("link")
        if not title or not url:
            continue

        slug = re.sub(r"[^a-z0-9]+", "-", title.lower()).strip("-")[:50]
        try:
            page = read_page(url, source_host, slug, title_from_h1=True)
        except Exception as e:  # noqa: BLE001
            print(f"  skipped ({e}): {url}", file=sys.stderr)
            continue
        if not page or not page["blocks"]:
            print(f"  no content: {url}", file=sys.stderr)
            continue

        out.append(
            {
                "title": title,
                "slug": slug,
                "date": field("pubDate"),
                "blocks": page["blocks"],
                "images": page["images"],
                "featured_image": page["featured_image"],
            }
        )
        print(f"  post: {title[:60]} ({len(page['blocks'])} blocks, {len(page['images'])} images)")

    return out


def main():
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--source", default="https://nabu-gruppe5.jimdofree.com", help="Jimdo site of a NABU group")
    p.add_argument("--posts", type=int, default=4, help="number of posts from the blog feed (default 4)")
    args = p.parse_args()

    source = args.source.rstrip("/")
    source_host = re.sub(r"^https?://", "", source).split("/")[0]

    IMAGES.mkdir(parents=True, exist_ok=True)

    print(f"Source: {source}")
    print("Posts from the blog feed:")
    posts = read_feed(source, source_host, args.posts)

    # Paths as in the NABU web builder template. A missing page is skipped
    # rather than aborting the run.
    project_paths = [
        ("projects/lebensraum-kirchturm", "lebensraum-kirchturm"),
        ("projects/handys-f%C3%BCr-die-havel", "handys-fuer-die-havel"),
        ("projects/vogel-des-jahres", "vogel-des-jahres"),
    ]

    print("Project pages:")
    projects = []
    for path, slug in project_paths:
        try:
            page = read_page(f"{source}/{path}/", source_host, slug)
        except Exception as e:  # noqa: BLE001
            print(f"  skipped ({e}): {path}", file=sys.stderr)
            continue
        if not page or not page["blocks"]:
            print(f"  no content: {path}", file=sys.stderr)
            continue
        page["slug"] = slug
        projects.append(page)
        print(f"  project: {page['title'][:60]} ({len(page['blocks'])} blocks, {len(page['images'])} images)")

    print("Additional images (hero, teasers):")
    extra = []
    for path, stem in [("", "titelbild"), ("schutzgebiete/", "schutzgebiet")]:
        try:
            extra += harvest_images(f"{source}/{path}", stem)
        except Exception as e:  # noqa: BLE001
            print(f"  skipped ({e}): /{path}", file=sys.stderr)
    # Largest first: the importer hands out teaser images in this order.
    extra.sort(key=lambda image: image["width"] * image["height"], reverse=True)
    for image in extra:
        print(
            f"  {image['width']}x{image['height']}  flat={image['flat_share']:.2f}  "
            f"{image['file'][:40]}  {image['credit']}"
        )

    all_images = {}
    for entry in posts + projects:
        all_images.update(entry["images"])
    for image in extra:
        all_images.setdefault(image["file"], {"credit": image["credit"], "alt": image["alt"]})

    data = {
        "source": source,
        "hint": (
            "Testmaterial. Texte und Fotos gehören der Quellgruppe bzw. den in den "
            "Bildnachweisen genannten Personen und dürfen nicht veröffentlicht werden."
        ),
        "posts": posts,
        "projects": projects,
        "extra_images": extra,
        "images": all_images,
    }

    target = BASE / "content.json"
    target.write_text(json.dumps(data, ensure_ascii=False, indent=1), encoding="utf-8")

    size_kb = sum(f.stat().st_size for f in IMAGES.glob("*")) / 1024
    print(f"\n{target}")
    print(
        f"  {len(posts)} posts, {len(projects)} projects, "
        f"{len(all_images)} images ({size_kb:.0f} KB)"
    )


if __name__ == "__main__":
    main()
