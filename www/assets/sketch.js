const minDots = 10;
const sqPixelsPerDot = 200 * 200;

const minDist = 100;
const mouseRepelDist = 200;

const dots = [];

const [red, green, blue] = getBodyBackgroundColor();

function setup() {
    const canvas = createCanvas(windowWidth, windowHeight);
    canvas.parent('background');

    calibrateDotDensity();
}

function draw() {
    background(red, green, blue);
    for (let dot of dots) {
        dot.update();
        dot.draw();
    }
}

function windowResized() {
    const sx = windowWidth / width;
    const sy = windowHeight / height;

    for (let dot of dots) {
        dot.x *= sx;
        dot.y *= sy;
    }

    resizeCanvas(windowWidth, windowHeight);

    calibrateDotDensity();
}

function calibrateDotDensity() {
    let pixelsSq = width * height;
    let desiredDots = max(minDots, floor(pixelsSq / sqPixelsPerDot));

    while (dots.length < desiredDots) {
        dots.push(randomDot());
    }

    while (dots.length > desiredDots) {
        dots.splice(dots.length - 1, 1);
    }
}


function getBodyBackgroundColor() {
    const bodyStyle = window.getComputedStyle(document.body);
    const backgroundColor = bodyStyle.backgroundColor;
    return backgroundColor.match(/\d+/g).map(Number);
}

function randomDot() {
    return new Dot(
        random(width),
        random(height),
        random(-1, 1),
        random(-1, 1),
    );
}

class Dot {
    constructor(x, y, dx, dy) {
        this.x = x;
        this.y = y;
        this.dx = dx;
        this.dy = dy;
    }

    update() {
        if (this.x + this.dx < 0 || this.x + this.dx >= width) {
            this.dx *= -1;
        }

        if (this.y + this.dy < 0 || this.y + this.dy >= height) {
            this.dy *= -1;
        }

        this.x += this.dx;
        this.y += this.dy;

        const mouseDistSq = this.distanceSquared({x: mouseX, y: mouseY});
        if (mouseDistSq < mouseRepelDist * mouseRepelDist) {
            const mouseDist = sqrt(mouseDistSq);
            const mouseForce = map(mouseDist, 0, mouseRepelDist, 1, 0);

            const mouseAngle = atan2(mouseY - this.y, mouseX - this.x);
            const fx = cos(mouseAngle) * -mouseForce;
            const fy = sin(mouseAngle) * -mouseForce;

            this.x += fx;
            this.y += fy;
        }

        this.x = constrain(this.x, 0, width);
        this.y = constrain(this.y, 0, height);
    }

    draw() {
        for (let other of dots) {
            if (other === this) {
                continue;
            }

            if (this.x < other.x) {
                // Ensure only one dot draws the line.
                continue;
            }

            const distSq = this.distanceSquared(other);
            if (distSq < minDist * minDist) {
                const dist = sqrt(distSq);
                const alpha = map(dist, 0, minDist, 255, 0);
                stroke(200, alpha);
                line(this.x, this.y, other.x, other.y);
            }
        }

        noStroke();
        fill(255);
        ellipse(this.x, this.y, 4);
    }

    distanceSquared(other) {
        const dx = this.x - other.x;
        const dy = this.y - other.y;
        return dx * dx + dy * dy;
    }
}
