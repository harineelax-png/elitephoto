import cv2
import numpy as np
from fastapi import FastAPI, File, UploadFile, Query
from fastapi.middleware.cors import CORSMiddleware
import math
import io

app = FastAPI(
    title="Image Framer - AI/CV Slot Detection Service",
    description="High-precision Computer Vision microservice to detect photo slots & shapes (rectangle, rounded rectangle, circle, ellipse, heart) on templates and mockups.",
    version="1.0.0"
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

def classify_shape(cnt, w, h, area):
    """
    Classifies a contour into one of the supported frame shapes:
    'rectangle', 'rounded_rectangle', 'circle', 'ellipse', 'heart'
    """
    perimeter = cv2.arcLength(cnt, True)
    if perimeter == 0:
        return "rectangle"

    circularity = 4 * math.pi * (area / (perimeter * perimeter))
    aspect = w / max(1, h)
    hull = cv2.convexHull(cnt)
    hull_area = cv2.contourArea(hull)
    solidity = float(area) / max(1.0, hull_area)
    fill_ratio = float(area) / max(1.0, w * h)

    # 1. Circle: high circularity, balanced aspect ratio, high solidity
    if circularity > 0.78 and abs(aspect - 1.0) <= 0.18 and solidity > 0.90:
        return "circle"

    # 2. Ellipse / Oval: good circularity, asymmetric aspect ratio
    if circularity > 0.65 and solidity > 0.88 and (aspect < 0.82 or aspect > 1.22):
        return "ellipse"

    # 3. Heart detection:
    # A heart has a concave indentation at top center, lower solidity (0.65 - 0.85),
    # centroid shifted upward, and a fill ratio around 0.50 - 0.70 inside its bounding box.
    M = cv2.moments(cnt)
    if M["m00"] != 0:
        cy = M["m01"] / M["m00"]
        rect_y_center = h / 2.0
        # Centroid is distinctly in the top half
        if cy < (rect_y_center * 0.95) and solidity < 0.86 and fill_ratio < 0.72:
            return "heart"

    # 4. Rounded rectangle vs Rectangle
    if fill_ratio >= 0.88 and circularity < 0.78:
        if fill_ratio < 0.96 and solidity > 0.92:
            return "rounded_rectangle"
        return "rectangle"

    # Default to rectangle
    return "rectangle"


def process_image(img_bytes: bytes, sensitivity: int = 28):
    """
    Detects photo openings using bilateral filtering, multi-scale edge detection,
    and contour hierarchy filtering.
    """
    nparr = np.frombuffer(img_bytes, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_UNCHANGED)
    if img is None:
        return {"error": "Invalid image file", "slots": []}

    orig_h, orig_w = img.shape[:2]

    # Check for Alpha Channel (Clean PNG Digital Template)
    if img.shape[2] == 4 if len(img.shape) > 2 else False:
        alpha = img[:, :, 3]
        # Invert alpha: transparent areas (slots) become 255 (white)
        transparent_mask = (alpha < 50).astype(np.uint8) * 255
        if np.count_nonzero(transparent_mask) > (orig_w * orig_h * 0.01):
            contours, _ = cv2.findContours(transparent_mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
            slots = []
            img_area = orig_w * orig_h
            for cnt in contours:
                area = cv2.contourArea(cnt)
                if area < (img_area * 0.01) or area > (img_area * 0.75):
                    continue
                x, y, w, h = cv2.boundingRect(cnt)
                shape = classify_shape(cnt, w, h, area)
                slots.append({
                    "x": int(x),
                    "y": int(y),
                    "width": int(w),
                    "height": int(h),
                    "shape": shape,
                    "rotation": 0
                })
            if len(slots) > 0:
                return {"width": orig_w, "height": orig_h, "slots": slots}

    # BGR Photographic Frame / Mockup Processing
    if len(img.shape) == 3:
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    else:
        gray = img

    # Bilateral filter eliminates human faces, clothing texture and glare
    # while preserving strong frame border edges!
    filtered = cv2.bilateralFilter(gray, 9, 75, 75)

    # Edge detection
    low_thresh = max(15, sensitivity)
    high_thresh = min(200, low_thresh * 3)
    edges = cv2.Canny(filtered, low_thresh, high_thresh)

    # Morphological closing to seal frame borders
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (5, 5))
    closed = cv2.morphologyEx(edges, cv2.MORPH_CLOSE, kernel)

    contours, hierarchy = cv2.findContours(closed, cv2.RETR_TREE, cv2.CHAIN_APPROX_SIMPLE)
    img_area = orig_w * orig_h
    raw_slots = []

    for cnt in contours:
        area = cv2.contourArea(cnt)
        if area < (img_area * 0.0006) or area > (img_area * 0.70):
            continue

        x, y, w, h = cv2.boundingRect(cnt)
        # Avoid outer border touching
        if x <= 5 or y <= 5 or (x + w) >= (orig_w - 5) or (y + h) >= (orig_h - 5):
            continue

        shape = classify_shape(cnt, w, h, area)
        raw_slots.append({
            "x": int(x),
            "y": int(y),
            "width": int(w),
            "height": int(h),
            "shape": shape,
            "rotation": 0,
            "area": area
        })

    # Non-maximum suppression / overlap deduplication
    raw_slots.sort(key=lambda s: s["area"], reverse=True)
    kept_slots = []
    for s in raw_slots:
        overlap = False
        for k in kept_slots:
            ix = max(0, min(s["x"] + s["width"], k["x"] + k["width"]) - max(s["x"], k["x"]))
            iy = max(0, min(s["y"] + s["height"], k["y"] + k["height"]) - max(s["y"], k["y"]))
            inter = ix * iy
            min_a = min(s["width"] * s["height"], k["width"] * k["height"])
            if min_a > 0 and (inter / min_a) > 0.45:
                overlap = True
                break
        if not overlap:
            kept_slots.append(s)

    # Sort slots in standard reading order (top-to-bottom, left-to-right)
    kept_slots.sort(key=lambda s: (s["y"] // max(1, int(orig_h * 0.15)), s["x"]))

    # Clean up internal keys
    for s in kept_slots:
        s.pop("area", None)

    return {
        "width": orig_w,
        "height": orig_h,
        "slots": kept_slots
    }


@app.get("/")
def index():
    return {
        "status": "online",
        "service": "Image Framer AI/CV Slot Detection Microservice",
        "supported_shapes": ["rectangle", "rounded_rectangle", "circle", "ellipse", "heart"]
    }


@app.post("/detect-slots")
async def detect_slots(
    file: UploadFile = File(...),
    sensitivity: int = Query(28, ge=10, le=100)
):
    contents = await file.read()
    result = process_image(contents, sensitivity=sensitivity)
    return result


if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host="127.0.0.1", port=5000, reload=True)
