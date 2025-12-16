pipeline {
    agent any

    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 60, unit: 'MINUTES')
        skipDefaultCheckout(true)
    }

    environment {
        GIT_REPO              = "https://github.com/Anandreddy125/project-management.git"
        GIT_CREDENTIALS_ID    = "terra-github"
        DOCKER_CREDENTIALS_ID = "anand-dockerhub"
    }

    parameters {
        choice(
            name: 'BRANCH_PARAM',
            choices: ['staging', 'master'],
            description: 'Manual build branch (ignored for tag builds)'
        )
        booleanParam(
            name: 'ROLLBACK',
            defaultValue: false,
            description: 'Rollback using TARGET_VERSION'
        )
        string(
            name: 'TARGET_VERSION',
            defaultValue: '',
            description: 'Docker tag for rollback'
        )
    }

    triggers {
        githubPush()
    }

    stages {

        /* ---------------- CLEAN ---------------- */
        stage('Clean Workspace') {
            steps {
                cleanWs()
            }
        }

        /* ---------------- CHECKOUT (BRANCH or TAG) ---------------- */
        stage('Checkout Code') {
            steps {
                script {
                    def gitRef = env.GIT_BRANCH
                    def refName = ""
                    def isTag = false

                    if (gitRef?.startsWith("refs/tags/")) {
                        isTag = true
                        refName = gitRef.replace("refs/tags/", "")
                        echo "🔖 TAG trigger detected: ${refName}"
                    } else {
                        refName = params.BRANCH_PARAM
                        echo "🌿 BRANCH trigger detected: ${refName}"
                    }

                    checkout([
                        $class: 'GitSCM',
                        branches: [[
                            name: isTag ? "refs/tags/${refName}" : "*/${refName}"
                        ]],
                        userRemoteConfigs: [[
                            url: env.GIT_REPO,
                            credentialsId: env.GIT_CREDENTIALS_ID
                        ]]
                    ])

                    env.IS_TAG = isTag.toString()
                    env.REF_NAME = refName
                }
            }
        }

        /* ---------------- ENV SELECTION ---------------- */
        stage('Determine Environment') {
            steps {
                script {
                    if (env.IS_TAG == "true") {
                        // TAG = PRODUCTION
                        env.DEPLOY_ENV = "production"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.KUBERNETES_CREDENTIALS_ID = "k3s-report-staging"
                        env.DEPLOYMENT_FILE = "prod-reports.yaml"
                        env.DEPLOYMENT_NAME = "prod-reports-api"
                        env.TAG_TYPE = "release"
                    } else if (env.REF_NAME == "staging") {
                        // STAGING BRANCH
                        env.DEPLOY_ENV = "staging"
                        env.IMAGE_NAME = "anrs125/reports-tesing"
                        env.KUBERNETES_CREDENTIALS_ID = "reports-staging"
                        env.DEPLOYMENT_FILE = "staging-report.yaml"
                        env.DEPLOYMENT_NAME = "staging-reports-api"
                        env.TAG_TYPE = "commit"
                    } else {
                        error("Unsupported deployment source")
                    }

                    echo """
                    ===== DEPLOYMENT INFO =====
                    Source      : ${env.IS_TAG == "true" ? "TAG" : "BRANCH"}
                    Ref Name    : ${env.REF_NAME}
                    Environment : ${env.DEPLOY_ENV}
                    Image Repo  : ${env.IMAGE_NAME}
                    Tag Type    : ${env.TAG_TYPE}
                    ===========================
                    """
                }
            }
        }

        /* ---------------- TAG GENERATION ---------------- */
        stage('Generate Docker Tag') {
            steps {
                script {
                    def commitId = sh(
                        script: "git rev-parse --short HEAD",
                        returnStdout: true
                    ).trim()

                    if (params.ROLLBACK) {
                        if (!params.TARGET_VERSION?.trim()) {
                            error("Rollback requested but TARGET_VERSION not provided")
                        }
                        env.IMAGE_TAG = params.TARGET_VERSION.trim()
                    }
                    else if (env.TAG_TYPE == "commit") {
                        env.IMAGE_TAG = "staging-${commitId}"
                    }
                    else {
                        // TAG BUILD → PROD
                        env.IMAGE_TAG = env.REF_NAME
                    }

                    echo "🚀 FINAL DOCKER TAG: ${env.IMAGE_TAG}"
                }
            }
        }

        /* ---------------- DOCKER LOGIN ---------------- */
        stage('Docker Login') {
            steps {
                withCredentials([
                    usernamePassword(
                        credentialsId: env.DOCKER_CREDENTIALS_ID,
                        usernameVariable: 'DOCKER_USER',
                        passwordVariable: 'DOCKER_PASSWORD'
                    )
                ]) {
                    sh "echo ${DOCKER_PASSWORD} | docker login -u ${DOCKER_USER} --password-stdin"
                }
            }
        }

        /* ---------------- BUILD & PUSH ---------------- */
        stage('Docker Build & Push') {
            when {
                expression { return !params.ROLLBACK }
            }
            steps {
                script {
                    def imageFull = "${env.IMAGE_NAME}:${env.IMAGE_TAG}"
                    sh """
                        docker build --no-cache -t ${imageFull} .
                        docker push ${imageFull}
                        docker logout
                    """
                }
            }
        }
    }
}
