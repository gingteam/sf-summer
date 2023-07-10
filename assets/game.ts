import 'kaboom/global'
import kaboom, { AreaComp, GameObj, PosComp, SpriteComp, Vec2, ZComp } from 'kaboom'
import { js } from 'easystarjs'

const WIDTH = 800
const HEIGHT = 400

const game = kaboom({
  // eslint-disable-next-line
  // @ts-ignore-next-line
  canvas: document.getElementById('game'),
  width: WIDTH,
  height: HEIGHT,
  background: [0, 0, 0, 0]
})

const TILE_WIDTH = 68
const HALF_TILE_WIDTH = TILE_WIDTH / 2
const TILE_HEIGHT = 36
const HALF_TILE_HEIGHT = TILE_HEIGHT / 2
const X_START = WIDTH / 2
const Y_START = 80

function convertToIsometric (x: number, y: number): Vec2 {
  const xScreen = X_START + (x - y) * HALF_TILE_WIDTH
  const yScreen = Y_START + (x + y) * HALF_TILE_HEIGHT

  return vec2(xScreen, yScreen)
}

loadSprite('grass', 'sprites/grass.png', {
  sliceX: 1,
  sliceY: 4
})

loadSprite('townie', 'sprites/townie.png', {
  sliceX: 1,
  sliceY: 8,
  anims: {
    wait: { from: 0, to: 0 },
    down: { from: 0, to: 1 },
    right: { from: 2, to: 3 },
    up: { from: 4, to: 5 },
    left: { from: 6, to: 7 }
  }
})

loadSprite('lumberjack', 'sprites/lumberjack.png', {
  sliceX: 1,
  sliceY: 3,
  anims: {
    house: { from: 0, to: 2, loop: true, speed: 2 }
  }
})

const map: Array<number>[] = []
const isomap: Array<Vec2>[] = []

for (let x = 0; x < 8; x++) {
  map[x] = []
  isomap[x] = []
  for (let y = 0; y < 8; y++) {
    const isopoint: Vec2 = convertToIsometric(x, y)
    map[x][y] = 0
    isomap[x][y] = isopoint
    game.add([
      'grass',
      sprite('grass', { frame: 1 }),
      pos(isopoint),
      anchor('bot'),
      area()
    ])
  }
}

const pathFinder = new js() // eslint-disable-line
pathFinder.setGrid(map)
pathFinder.enableSync()
pathFinder.setAcceptableTiles([0])

game.add([
  'build',
  sprite('lumberjack', { anim: 'house' }),
  pos(convertToIsometric(0, 0)),
  area(),
  anchor('bot'),
  z(2)
])

game.add([
  'build',
  sprite('lumberjack', { anim: 'house' }),
  pos(convertToIsometric(6, 6)),
  area(),
  anchor('bot'),
  z(2)
])

type TownieUpateComp = {
  path: {x: number, y: number}[]
  current: number
  state: 'go' | 'wait' | 'working'
  getPosVec2(index: number): Vec2
  in(pos: Vec2): boolean
  start(): void
}

function townieupdate (): TownieUpateComp {
  return {
    path: [],
    current: 0,
    state: 'wait',
    getPosVec2 (index: number): Vec2 {
      const { x, y } = this.path[index]

      return vec2(x, y)
    },
    in (this: PosComp, pos: Vec2) {
      return this.pos.eq(pos)
    },
    start (this: GameObj<TownieUpateComp|SpriteComp|PosComp|AreaComp|ZComp>) {
      this.onCollideUpdate('build', (build) => {
        const buildZ = build as GameObj<PosComp>
        if (this.pos.dist(buildZ.pos) > 30) {
          this.z = 1

          return
        }

        this.z = 3
      })

      this.onUpdate(() => {
        if (this.state === 'wait') {
          this.play('wait')
          return
        }

        if (this.state === 'go' && this.path.length > 0) {
          const current = this.getPosVec2(this.current)
          const { x, y } = current
          const pos = isomap[x][y]

          if (this.in(pos)) {
            if (this.current < this.path.length - 1) {
              const next = this.getPosVec2(this.current + 1)
              this.play(getTownieDirection(next.sub(current)), { loop: true, speed: 5 })
              this.current++
            } else {
              this.state = 'wait'
              wait(5, () => {
                this.state = 'go'
              })
              this.path.reverse()
              this.current = 0
            }
          }

          this.moveTo(pos, 50)
        }
      })
    }
  }
}

const townie = game.add([
  'townie',
  sprite('townie', { frame: 0 }),
  pos(convertToIsometric(0, 0)),
  area(),
  anchor('bot'),
  z(1),
  townieupdate()
])

townie.state = 'go'
pathFinder.findPath(0, 0, 6, 6, function (path) {
  if (path !== null) {
    townie.path = path
  }
})
townie.start()
pathFinder.calculate()

function getTownieDirection (dir: Vec2) {
  if (dir.x === -1) {
    return 'left'
  }

  if (dir.x === 1) {
    return 'right'
  }

  if (dir.y === -1) {
    return 'up'
  }

  return 'down'
}
